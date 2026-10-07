<?php
class WP_Error { public $c; public function __construct($c='',$m=''){ $this->c=$c; } public function get_error_message(){return 'err';} }
function is_wp_error($v){return $v instanceof WP_Error;} function sanitize_key($v){return $v;} function current_user_can($c){return true;}
function get_option($k,$d=[]){global $opts; return $opts[$k]??$d;} function update_option($k,$v,$a=false){global $opts;$opts[$k]=$v;return true;}
function wp_mkdir_p($p){return true;} function wp_generate_password(){return 'abc123';}
function copy_dir($a,$b){global $wp_filesystem; if(!$wp_filesystem) return new WP_Error('fs'); return true;}
function WP_Filesystem(){global $wp_filesystem; $wp_filesystem=new FakeFS(); return true;} class FakeFS {function delete($p,$r=false){return true;}}
function get_plugins(){return ['plug/main.php'=>['Version'=>'1.0']];} function is_plugin_active($f){global $active;return $active;} function activate_plugin($f){global $active;$active=true;return null;} function deactivate_plugins($f){global $active;$active=false;} function wp_clean_plugins_cache($c=false){}
$fake = sys_get_temp_dir().'/wp-plugin-deploy-fake/';
@mkdir($fake.'wp-admin/includes',0777,true);
@touch($fake.'wp-admin/includes/file.php');
@touch($fake.'wp-admin/includes/plugin.php');
define('ABSPATH',$fake); define('WP_PLUGIN_DIR',__DIR__.'/plugins'); define('WP_CONTENT_DIR',__DIR__.'/content');
require __DIR__.'/../includes/class-package-validator.php'; require __DIR__.'/../includes/class-filesystem.php'; require __DIR__.'/../includes/class-rollback-manager.php';
class Store {function record($e){}}
class Backups {function get($s,$id=null){return ['id'=>'b1','path'=>__DIR__,'was_active'=>false];}}
$active=true; $mgr=new WP_Plugin_Deploy_Rollback_Manager(new Backups(),new Store());
$r=$mgr->rollback('plug','b1'); if(is_wp_error($r)){fwrite(STDERR,"FAIL rollback returned error\n");exit(1);} if($active!==false){fwrite(STDERR,"FAIL rollback must restore inactive state\n");exit(1);} echo "PASS rollback\n";
