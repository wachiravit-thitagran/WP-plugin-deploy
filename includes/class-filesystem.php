<?php
if ( ! defined( 'ABSPATH' ) && PHP_SAPI !== 'cli' ) { exit; }

class WP_Plugin_Deploy_Filesystem {
    public static function init( $writable_path = null ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';

        WP_Filesystem();
        global $wp_filesystem;

        if ( $wp_filesystem ) {
            return $wp_filesystem;
        }

        $path = $writable_path ?: WP_PLUGIN_DIR;
        if ( ! is_dir( $path ) || ! is_writable( $path ) ) {
            return new WP_Error( 'filesystem_failed', 'WordPress filesystem is unavailable and the target path is not directly writable.' );
        }

        if ( ! class_exists( 'WP_Filesystem_Direct' ) ) {
            $direct = ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
            if ( file_exists( $direct ) ) {
                require_once $direct;
            }
        }

        if ( ! class_exists( 'WP_Filesystem_Direct' ) ) {
            return new WP_Error( 'filesystem_failed', 'Direct WordPress filesystem implementation is unavailable.' );
        }

        $wp_filesystem = new WP_Filesystem_Direct( null );

        return $wp_filesystem ?: new WP_Error( 'filesystem_failed', 'Unable to initialize direct WordPress filesystem access.' );
    }
}
