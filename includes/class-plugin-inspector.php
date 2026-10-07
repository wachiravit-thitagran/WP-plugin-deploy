<?php
if ( ! defined( 'ABSPATH' ) && PHP_SAPI !== 'cli' ) { exit; }
class WP_Plugin_Deploy_Plugin_Inspector {
    private $store; private $backups;
    public function __construct( $store, $backups ) { $this->store=$store; $this->backups=$backups; }
    public function find_by_slug( $slug ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        foreach ( get_plugins() as $file => $data ) {
            if ( dirname( $file ) === $slug || ( '.' === dirname($file) && basename($file,'.php') === $slug ) ) { return array('file'=>$file,'data'=>$data); }
        }
        return null;
    }
    public function status( $slug ) {
        $found = $this->find_by_slug( $slug );
        $backups = array_map( static function($b){ return array('id'=>$b['id'],'created_at'=>$b['created_at'],'was_active'=>(bool)$b['was_active']); }, $this->backups->list($slug) );
        if ( ! $found ) { return array('plugin_slug'=>$slug,'installed'=>false,'active'=>false,'latest_deployment'=>$this->store->latest($slug),'backups'=>$backups); }
        return array('plugin_slug'=>$slug,'installed'=>true,'plugin_file'=>$found['file'],'version'=>$found['data']['Version'] ?? '','active'=>is_plugin_active($found['file']),'latest_deployment'=>$this->store->latest($slug),'backups'=>$backups);
    }
}
