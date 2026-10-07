<?php
if ( ! defined( 'ABSPATH' ) && PHP_SAPI !== 'cli' ) { exit; }
class WP_Plugin_Deploy_Backup_Manager {
    const OPTION = 'wp_plugin_deploy_backups';
    const KEEP = 3;
    private $root;
    public function __construct() { $this->root = WP_CONTENT_DIR . '/wp-plugin-deploy-backups'; }
    public function create( $slug, $source_dir, $was_active ) {
        if ( ! WP_Plugin_Deploy_Package_Validator::is_valid_slug( $slug ) || ! is_dir( $source_dir ) ) {
            return new WP_Error( 'backup_failed', 'Invalid plugin backup source.' );
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
        global $wp_filesystem;
        if ( ! $wp_filesystem ) { return new WP_Error( 'backup_failed', 'WordPress filesystem is unavailable.' ); }
        $this->ensure_root();
        $id = gmdate( 'YmdHis' ) . '-' . wp_generate_password( 6, false, false );
        $dest = $this->root . '/' . $slug . '/' . $id;
        if ( ! wp_mkdir_p( dirname( $dest ) ) ) { return new WP_Error( 'backup_failed', 'Unable to create backup directory.' ); }
        $result = copy_dir( $source_dir, $dest );
        if ( is_wp_error( $result ) ) { return new WP_Error( 'backup_failed', $result->get_error_message() ); }
        $meta = array( 'id'=>$id, 'plugin_slug'=>$slug, 'path'=>$dest, 'was_active'=>(bool)$was_active, 'created_at'=>gmdate('c') );
        $all = get_option( self::OPTION, array() );
        $all[$slug] = array_merge( array($meta), $all[$slug] ?? array() );
        $old = array_slice( $all[$slug], self::KEEP );
        $all[$slug] = array_slice( $all[$slug], 0, self::KEEP );
        update_option( self::OPTION, $all, false );
        foreach ( $old as $item ) { if ( ! empty( $item['path'] ) ) { $wp_filesystem->delete( $item['path'], true ); } }
        return $meta;
    }
    public function list( $slug ) {
        $all = get_option( self::OPTION, array() );
        return $all[ sanitize_key($slug) ] ?? array();
    }
    public function get( $slug, $id = null ) {
        $items = $this->list( $slug );
        if ( null === $id ) { return $items[0] ?? new WP_Error( 'backup_not_found', 'No backup is available.' ); }
        foreach ( $items as $item ) { if ( hash_equals( (string)$item['id'], (string)$id ) ) { return $item; } }
        return new WP_Error( 'backup_not_found', 'Requested backup was not found.' );
    }
    private function ensure_root() {
        wp_mkdir_p( $this->root );
        if ( is_dir( $this->root ) ) {
            if ( ! file_exists( $this->root . '/index.php' ) ) { @file_put_contents( $this->root . '/index.php', "<?php\n// Silence is golden.\n" ); }
            if ( ! file_exists( $this->root . '/.htaccess' ) ) { @file_put_contents( $this->root . '/.htaccess', "Require all denied\n" ); }
        }
    }
}
