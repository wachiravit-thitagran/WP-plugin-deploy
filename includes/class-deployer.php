<?php
if ( ! defined( 'ABSPATH' ) && PHP_SAPI !== 'cli' ) { exit; }

class WP_Plugin_Deploy_Deployer {
    private $resolver;
    private $validator;
    private $backups;
    private $store;
    private $inspector;
    private $rollback;
    private $native_upgrader;

    public function __construct( $resolver, $validator, $backups, $store, $inspector, $rollback, $native_upgrader = null ) {
        $this->resolver        = $resolver;
        $this->validator       = $validator;
        $this->backups         = $backups;
        $this->store           = $store;
        $this->inspector       = $inspector;
        $this->rollback        = $rollback;
        $this->native_upgrader = $native_upgrader;
    }

    public function deploy( array $request ) {
        $source = $this->resolver->resolve( $request );
        if ( is_wp_error( $source ) ) {
            return $source;
        }

        $expected = isset( $request['plugin_slug'] ) ? (string) $request['plugin_slug'] : '';
        if ( '' !== $expected && ! WP_Plugin_Deploy_Package_Validator::is_valid_slug( $expected ) ) {
            return new WP_Error( 'invalid_plugin_package', 'Invalid plugin slug.' );
        }

        $target_slug = $expected ?: ( $source['plugin_slug_hint'] ?? '' );
        if ( '' === $target_slug || ! WP_Plugin_Deploy_Package_Validator::is_valid_slug( $target_slug ) ) {
            return new WP_Error( 'invalid_plugin_package', 'Unable to determine a valid plugin slug.' );
        }

        $existing = $this->inspector->find_by_slug( $target_slug );

        if ( $existing && ! current_user_can( 'update_plugins' ) ) {
            return new WP_Error( 'permission_denied', 'You cannot update plugins.' );
        }

        if ( ! $existing && ! current_user_can( 'install_plugins' ) ) {
            return new WP_Error( 'permission_denied', 'You cannot install plugins.' );
        }

        if ( ! empty( $request['activate'] ) && ! current_user_can( 'activate_plugins' ) ) {
            return new WP_Error( 'permission_denied', 'You cannot activate plugins.' );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        $plugin_upgrader_file = ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
        if ( file_exists( $plugin_upgrader_file ) ) {
            require_once $plugin_upgrader_file;
        }

        $tmp = download_url( $source['download_url'], 60 );
        if ( is_wp_error( $tmp ) ) {
            return new WP_Error( 'download_failed', $tmp->get_error_message() );
        }

        $valid = $this->validator->validate( $tmp, $target_slug );
        if ( is_wp_error( $valid ) ) {
            @unlink( $tmp );
            return $valid;
        }

        $backup = null;
        if ( $existing ) {
            $target_dir = WP_PLUGIN_DIR . '/' . $target_slug;
            $backup     = $this->backups->create(
                $target_slug,
                $target_dir,
                is_plugin_active( $existing['file'] )
            );

            if ( is_wp_error( $backup ) ) {
                @unlink( $tmp );
                return $backup;
            }
        }

        $native = $this->native_upgrader;
        if ( ! $native ) {
            if ( ! class_exists( 'WP_Plugin_Deploy_Native_Upgrader' ) ) {
                @unlink( $tmp );
                return new WP_Error( 'upgrader_unavailable', 'Native plugin upgrader adapter is unavailable.' );
            }
            $native = new WP_Plugin_Deploy_Native_Upgrader();
        }

        $result = $native->install_or_update(
            $tmp,
            $target_slug,
            ! empty( $request['activate'] )
        );

        @unlink( $tmp );

        if ( is_wp_error( $result ) ) {
            $rolled_back   = false;
            $rollback_error = null;

            if ( $backup ) {
                $rollback = $this->rollback->rollback( $target_slug, $backup['id'] );
                if ( is_wp_error( $rollback ) ) {
                    $rollback_error = $rollback->get_error_message();
                } else {
                    $rolled_back = true;
                }
            }

            $event = array(
                'plugin_slug'      => $target_slug,
                'action'           => $existing ? 'update' : 'install',
                'status'           => $rolled_back ? 'rolled_back' : 'failure',
                'source_type'      => $source['source_type'],
                'source_reference' => $source['source_reference'],
                'ref'              => $source['ref'],
                'error'            => $result->get_error_message(),
                'rolled_back'      => $rolled_back,
            );
            $this->store->record( $event );

            if ( $rollback_error ) {
                return new WP_Error(
                    'rollback_failed',
                    $result->get_error_message() . ' Rollback failed: ' . $rollback_error,
                    array( 'original_error' => $result->get_error_message() )
                );
            }

            return new WP_Error(
                $result->get_error_code() ?: 'deployment_failed',
                $result->get_error_message(),
                array( 'rolled_back' => $rolled_back )
            );
        }

        $installed = $this->inspector->find_by_slug( $target_slug );
        if ( ! $installed ) {
            return new WP_Error( 'verification_failed', 'WordPress could not discover the deployed plugin.' );
        }

        $event = array(
            'plugin_slug'      => $target_slug,
            'action'           => $existing ? 'update' : 'install',
            'status'           => 'success',
            'source_type'      => $source['source_type'],
            'source_reference' => $source['source_reference'],
            'ref'              => $source['ref'],
            'version'          => $installed['data']['Version'] ?? '',
            'backup_id'        => $backup['id'] ?? null,
        );
        $this->store->record( $event );

        return $event + array(
            'installed'   => true,
            'active'      => is_plugin_active( $installed['file'] ),
            'plugin_file' => $installed['file'],
        );
    }
}
