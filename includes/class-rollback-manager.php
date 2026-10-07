<?php
if ( ! defined( 'ABSPATH' ) && PHP_SAPI !== 'cli' ) { exit; }
class WP_Plugin_Deploy_Rollback_Manager {
    private $backups; private $store;
    public function __construct( $backups, $store ) { $this->backups=$backups; $this->store=$store; }
    public function rollback( $slug, $backup_id = null ) {
        if ( ! WP_Plugin_Deploy_Package_Validator::is_valid_slug( $slug ) ) { return new WP_Error('invalid_plugin_package','Invalid plugin slug.'); }
        if ( ! current_user_can('update_plugins') ) { return new WP_Error('permission_denied','You cannot update plugins.'); }
        $backup = $this->backups->get($slug,$backup_id); if ( is_wp_error($backup) ) return $backup;
        if ( empty($backup['path']) || ! is_dir($backup['path']) ) return new WP_Error('rollback_failed','Backup files are missing.');
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $filesystem = WP_Plugin_Deploy_Filesystem::init( WP_PLUGIN_DIR );
        if ( is_wp_error( $filesystem ) ) return new WP_Error('rollback_failed',$filesystem->get_error_message());
        global $wp_filesystem;
        $target = WP_PLUGIN_DIR . '/' . $slug;
        foreach ( get_plugins() as $plugin_file => $plugin_data ) { if ( dirname( $plugin_file ) === $slug ) { if ( is_plugin_active( $plugin_file ) ) { deactivate_plugins( $plugin_file ); } break; } }
        if ( is_dir($target) && ! $wp_filesystem->delete($target,true) ) return new WP_Error('rollback_failed','Unable to remove current plugin files.');
        $copied = copy_dir($backup['path'],$target); if ( is_wp_error($copied) ) return new WP_Error('rollback_failed',$copied->get_error_message());
        wp_clean_plugins_cache(true);
        $file = $this->find_main_file($slug);
        if ( $backup['was_active'] && $file ) { $a=activate_plugin($file); if(is_wp_error($a)) return new WP_Error('rollback_failed',$a->get_error_message()); }
        elseif ( ! $backup['was_active'] && $file && is_plugin_active( $file ) ) { deactivate_plugins( $file ); }
        $event=array('plugin_slug'=>$slug,'action'=>'rollback','status'=>'success','backup_id'=>$backup['id']); $this->store->record($event);
        return $event;
    }
    private function find_main_file($slug){ foreach(get_plugins() as $file=>$data){ if(dirname($file)===$slug) return $file; } return null; }
}
