<?php
if ( ! defined( 'ABSPATH' ) && PHP_SAPI !== 'cli' ) { exit; }

class WP_Plugin_Deploy_Package_Validator {
    public static function is_valid_slug( $slug ) {
        return is_string( $slug ) && preg_match( '/^[a-z0-9][a-z0-9-]*$/', $slug );
    }

    public static function is_safe_archive_path( $path ) {
        if ( ! is_string( $path ) || '' === $path ) { return false; }
        $path = str_replace( '\\', '/', $path );
        if ( 0 === strpos( $path, '/' ) || preg_match( '/^[A-Za-z]:\//', $path ) ) { return false; }
        foreach ( explode( '/', $path ) as $segment ) {
            if ( '..' === $segment ) { return false; }
        }
        return true;
    }

    public function validate( $zip_path, $expected_slug = null ) {
        if ( null !== $expected_slug && ! self::is_valid_slug( $expected_slug ) ) {
            return new WP_Error( 'invalid_plugin_package', 'Invalid expected plugin slug.' );
        }
        if ( ! is_string( $zip_path ) || ! is_file( $zip_path ) ) {
            return new WP_Error( 'invalid_archive', 'ZIP package does not exist.' );
        }

        $entries = $this->list_entries( $zip_path );
        if ( is_wp_error( $entries ) ) { return $entries; }
        foreach ( $entries as $entry ) {
            if ( ! self::is_safe_archive_path( $entry ) ) {
                return new WP_Error( 'invalid_archive', 'Archive contains an unsafe path.' );
            }
        }
        return array( 'entries' => $entries, 'expected_slug' => $expected_slug );
    }

    private function list_entries( $zip_path ) {
        if ( class_exists( 'ZipArchive' ) ) {
            $zip = new ZipArchive();
            if ( true !== $zip->open( $zip_path ) ) { return new WP_Error( 'invalid_archive', 'Unable to open ZIP archive.' ); }
            $entries = array();
            for ( $i = 0; $i < $zip->numFiles; $i++ ) { $entries[] = $zip->getNameIndex( $i ); }
            $zip->close();
            return $entries;
        }
        if ( defined( 'ABSPATH' ) ) {
            $pcl = ABSPATH . 'wp-admin/includes/class-pclzip.php';
            if ( file_exists( $pcl ) ) { require_once $pcl; }
            if ( class_exists( 'PclZip' ) ) {
                $archive = new PclZip( $zip_path );
                $list = $archive->listContent();
                if ( 0 === $list ) { return new WP_Error( 'invalid_archive', 'Unable to read ZIP archive.' ); }
                return array_values( array_map( static function( $item ) { return $item['filename']; }, $list ) );
            }
        }
        return new WP_Error( 'invalid_archive', 'No ZIP reader is available.' );
    }
}
