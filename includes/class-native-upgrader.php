<?php
if ( ! defined( 'ABSPATH' ) && PHP_SAPI !== 'cli' ) { exit; }

if ( class_exists( 'WP_Upgrader_Skin' ) && ! class_exists( 'WP_Plugin_Deploy_Upgrader_Skin' ) ) {
    class WP_Plugin_Deploy_Upgrader_Skin extends WP_Upgrader_Skin {
        public function feedback( $feedback, ...$args ) {}
        public function header() {}
        public function footer() {}
    }
}

class WP_Plugin_Deploy_Native_Upgrader {
    public function install_or_update( $package, $expected_slug, $activate = false ) {
        if ( ! class_exists( 'Plugin_Upgrader' ) ) {
            return new WP_Error( 'upgrader_unavailable', 'WordPress Plugin_Upgrader is unavailable.' );
        }

        $skin = class_exists( 'WP_Plugin_Deploy_Upgrader_Skin' )
            ? new WP_Plugin_Deploy_Upgrader_Skin()
            : new WP_Upgrader_Skin();

        $upgrader = new Plugin_Upgrader( $skin );
        $result   = $upgrader->install(
            $package,
            array(
                'overwrite_package'  => true,
                'clear_update_cache' => true,
            )
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( false === $result ) {
            return new WP_Error( 'install_failed', 'WordPress Plugin_Upgrader could not install the package.' );
        }

        $plugin_file = method_exists( $upgrader, 'plugin_info' ) ? $upgrader->plugin_info() : '';
        if ( ! is_string( $plugin_file ) || '' === $plugin_file ) {
            return new WP_Error( 'verification_failed', 'WordPress could not determine the installed plugin file.' );
        }

        if ( $expected_slug && dirname( $plugin_file ) !== $expected_slug ) {
            return new WP_Error(
                'plugin_slug_mismatch',
                sprintf( 'Installed plugin slug "%s" did not match expected slug "%s".', dirname( $plugin_file ), $expected_slug )
            );
        }

        if ( $activate ) {
            $activation = activate_plugin( $plugin_file );
            if ( is_wp_error( $activation ) ) {
                return $activation;
            }
            if ( ! is_plugin_active( $plugin_file ) ) {
                return new WP_Error( 'activation_failed', 'Plugin activation could not be verified.' );
            }
        }

        wp_clean_plugins_cache( true );

        return array(
            'plugin_file' => $plugin_file,
            'active'      => is_plugin_active( $plugin_file ),
        );
    }
}
