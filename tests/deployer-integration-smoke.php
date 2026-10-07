<?php
function fail_test($message){ fwrite(STDERR,"FAIL: $message\n"); exit(1); }
function assert_true($condition,$message){ if(!$condition) fail_test($message); }

class WP_Error {
    private $code; private $message; private $data;
    public function __construct($code='',$message='',$data=null){$this->code=$code;$this->message=$message;$this->data=$data;}
    public function get_error_code(){return $this->code;}
    public function get_error_message(){return $this->message;}
    public function get_error_data(){return $this->data;}
}
function is_wp_error($v){return $v instanceof WP_Error;}
function sanitize_key($key){return strtolower(preg_replace('/[^a-z0-9_\-]/','',(string)$key));}
function trailingslashit($v){return rtrim($v,'/\\').'/';}
function get_temp_dir(){return sys_get_temp_dir().'/';}
function wp_generate_password($length=12,$special=true,$extra=false){return 'abc123def4';}
function wp_mkdir_p($path){return is_dir($path) || mkdir($path,0777,true);}
function current_user_can($cap){global $caps; return !empty($caps[$cap]);}
function wp_clean_plugins_cache($clear=false){}
function WP_Filesystem(){global $wp_filesystem,$wp_filesystem_should_fail; if(!empty($wp_filesystem_should_fail)){ $wp_filesystem=null; return false; } $wp_filesystem=new FakeFS(); return true;}
class FakeFS {
    public function delete($path,$recursive=false){
        if(is_dir($path)){ rrmdir($path); }
        elseif(file_exists($path)){ @unlink($path); }
        return true;
    }
}
class WP_Filesystem_Direct extends FakeFS { public function __construct($args=null){} }
function rrmdir($dir){
    if(!is_dir($dir)) return;
    $items=scandir($dir);
    foreach($items as $item){
        if($item==='.'||$item==='..') continue;
        $path=$dir.'/'.$item;
        if(is_dir($path)) rrmdir($path); else @unlink($path);
    }
    @rmdir($dir);
}
function download_url($url,$timeout=60){
    $tmp=tempnam(sys_get_temp_dir(),'wpd-');
    file_put_contents($tmp,'fake zip');
    return $tmp;
}
function unzip_file($zip,$stage){
    global $fixture_slug,$fixture_version,$wp_filesystem,$require_filesystem_before_unzip;
    if(!empty($require_filesystem_before_unzip) && !$wp_filesystem){ return new WP_Error('filesystem_failed','Could not access filesystem.'); }
    $root=$stage.'/github-generated-root';
    mkdir($root,0777,true);
    file_put_contents($root.'/plugin.php',"<?php\n/*\nPlugin Name: Fixture Plugin\nVersion: ".$fixture_version."\n*/\n");
    return true;
}
function copy_dir($source,$target){
    global $installed_plugins;
    if(!is_dir($target)) mkdir($target,0777,true);
    foreach(scandir($source) as $item){
        if($item==='.'||$item==='..') continue;
        copy($source.'/'.$item,$target.'/'.$item);
    }
    $installed_plugins['fixture-plugin']=array('file'=>'fixture-plugin/plugin.php','data'=>array('Version'=>'2.0.0'));
    return true;
}
function get_plugins(){
    global $installed_plugins;
    $plugins=array();
    foreach($installed_plugins as $plugin){
        $plugins[$plugin['file']]=$plugin['data'];
    }
    return $plugins;
}
function is_plugin_active($file){global $active_plugins; return in_array($file,$active_plugins,true);}
function activate_plugin($file){
    global $activation_should_fail,$active_plugins;
    if($activation_should_fail) return new WP_Error('activation_failed','Activation failed by test fixture.');
    if(!in_array($file,$active_plugins,true)) $active_plugins[]=$file;
    return null;
}
function deactivate_plugins($file){global $active_plugins; $active_plugins=array_values(array_diff($active_plugins,array($file)));}

$fake_wp=sys_get_temp_dir().'/wpd-wp-'.getmypid().'/';
@mkdir($fake_wp.'wp-admin/includes',0777,true);
foreach(array('file.php','plugin.php','class-wp-upgrader.php') as $f){ @touch($fake_wp.'wp-admin/includes/'.$f); }
define('ABSPATH',$fake_wp);
define('WP_PLUGIN_DIR',$fake_wp.'wp-content/plugins');
define('WP_CONTENT_DIR',$fake_wp.'wp-content');
@mkdir(WP_PLUGIN_DIR,0777,true);

require __DIR__.'/../includes/class-package-validator.php';
require __DIR__.'/../includes/class-filesystem.php';
require __DIR__.'/../includes/class-deployer.php';
require __DIR__.'/../includes/class-plugin-inspector.php';

class FakeResolver {
    public function resolve(array $request){
        return array(
            'source_type'=>'github',
            'download_url'=>'https://api.github.com/repos/acme/fixture-plugin/zipball/main',
            'source_reference'=>'https://github.com/acme/fixture-plugin',
            'ref'=>'main',
            'plugin_slug_hint'=>'fixture-plugin',
        );
    }
}
class FakeValidator {
    public function validate($zip,$expected=null){return array('entries'=>array('root/plugin.php'));}
}
class FakeBackups {
    public $created=0;
    public function create($slug,$dir,$active){$this->created++; return array('id'=>'backup-1','plugin_slug'=>$slug,'path'=>$dir,'was_active'=>$active);}
}
class FakeStore {
    public $events=array();
    public function record(array $event){$this->events[]=$event;}
    public function latest($slug){return $this->events ? $this->events[count($this->events)-1] : null;}
}
class FakeInspector {
    public function find_by_slug($slug){
        global $installed_plugins;
        return $installed_plugins[$slug] ?? null;
    }
}
class FakeNativeUpgrader {
    public function install_or_update($package,$slug,$activate=false){
        global $installed_plugins,$active_plugins,$activation_should_fail;
        if($activation_should_fail){ return new WP_Error('activation_failed','Activation failed by native upgrader fixture.'); }
        $installed_plugins[$slug]=array('file'=>$slug.'/plugin.php','data'=>array('Version'=>'2.0.0'));
        if($activate && !in_array($slug.'/plugin.php',$active_plugins,true)) $active_plugins[]=$slug.'/plugin.php';
        return array('plugin_file'=>$slug.'/plugin.php','active'=>$activate);
    }
}
class FakeRollback {
    public $calls=0;
    public function rollback($slug,$id=null){$this->calls++; return array('plugin_slug'=>$slug,'action'=>'rollback','status'=>'success','backup_id'=>$id);}
}
class FakeBackupList {
    public function list($slug){
        return array(array('id'=>'b1','created_at'=>'2026-10-07T00:00:00Z','was_active'=>true,'path'=>'/secret/server/path'));
    }
}

function make_deployer(&$backups,&$store,&$rollback){
    $backups=new FakeBackups();
    $store=new FakeStore();
    $rollback=new FakeRollback();
    return new WP_Plugin_Deploy_Deployer(new FakeResolver(),new FakeValidator(),$backups,$store,new FakeInspector(),$rollback,new FakeNativeUpgrader());
}
function reset_fixture(){
    global $caps,$installed_plugins,$active_plugins,$activation_should_fail,$fixture_slug,$fixture_version,$wp_filesystem_should_fail,$require_filesystem_before_unzip,$wp_filesystem;
    $caps=array('install_plugins'=>true,'update_plugins'=>true,'activate_plugins'=>true);
    $installed_plugins=array();
    $active_plugins=array();
    $activation_should_fail=false;
    $fixture_slug='fixture-plugin';
    $fixture_version='2.0.0';
    $wp_filesystem_should_fail=false;
    $require_filesystem_before_unzip=false;
    $wp_filesystem=null;
    rrmdir(WP_PLUGIN_DIR.'/fixture-plugin');
}

reset_fixture();
$deployer=make_deployer($backups,$store,$rollback);
$result=$deployer->deploy(array('source'=>'https://github.com/acme/fixture-plugin','activate'=>true));
assert_true(!is_wp_error($result),'fresh install should succeed');
assert_true(($result['plugin_slug']??'')==='fixture-plugin','GitHub repo slug hint should become stable plugin slug');
assert_true(($result['status']??'')==='success','fresh install should record success');
assert_true(($result['active']??false)===true,'fresh install should activate when requested');
assert_true($backups->created===0,'fresh install must not create update backup');
assert_true($rollback->calls===0,'fresh install must not rollback');
echo "PASS fresh install\n";

reset_fixture();
$caps['install_plugins']=false;
$deployer=make_deployer($backups,$store,$rollback);
$result=$deployer->deploy(array('source'=>'https://github.com/acme/fixture-plugin'));
assert_true(is_wp_error($result),'permission denial should return WP_Error');
assert_true($result->get_error_code()==='permission_denied','permission denial error code');
assert_true(!is_dir(WP_PLUGIN_DIR.'/fixture-plugin'),'permission denial must happen before filesystem mutation');
echo "PASS permission gate\n";

reset_fixture();
$wp_filesystem_should_fail=true;
$deployer=make_deployer($backups,$store,$rollback);
$result=$deployer->deploy(array('source'=>'https://github.com/acme/fixture-plugin','activate'=>true));
assert_true(!is_wp_error($result),'writable direct filesystem fallback should allow deploy when WP_Filesystem bootstrap fails');
assert_true(($result['active']??false)===true,'direct filesystem fallback deployment should activate plugin');
echo "PASS direct filesystem fallback\n";

reset_fixture();
$wp_filesystem_should_fail=true;
$require_filesystem_before_unzip=true;
$deployer=make_deployer($backups,$store,$rollback);
$result=$deployer->deploy(array('source'=>'https://github.com/acme/fixture-plugin','activate'=>true));
assert_true(!is_wp_error($result),'filesystem must be initialized before unzip_file is called');
echo "PASS filesystem before unzip\n";

reset_fixture();
@mkdir(WP_PLUGIN_DIR.'/fixture-plugin',0777,true);
file_put_contents(WP_PLUGIN_DIR.'/fixture-plugin/plugin.php',"<?php\n/* Plugin Name: Fixture Plugin\nVersion: 1.0.0 */\n");
$installed_plugins['fixture-plugin']=array('file'=>'fixture-plugin/plugin.php','data'=>array('Version'=>'1.0.0'));
$active_plugins[]='fixture-plugin/plugin.php';
$activation_should_fail=true;
$deployer=make_deployer($backups,$store,$rollback);
$result=$deployer->deploy(array('source'=>'https://github.com/acme/fixture-plugin','plugin_slug'=>'fixture-plugin','activate'=>true));
assert_true(is_wp_error($result),'activation failure should return WP_Error');
assert_true($result->get_error_code()==='verification_failed','activation failure maps to verification_failed after successful rollback');
assert_true(($result->get_error_data()['rolled_back']??false)===true,'activation failure should report rolled_back=true');
assert_true($backups->created===1,'update must create exactly one backup');
assert_true($rollback->calls===1,'activation failure after replacement must invoke rollback exactly once');
echo "PASS activation rollback\n";

reset_fixture();
$installed_plugins['fixture-plugin']=array('file'=>'fixture-plugin/plugin.php','data'=>array('Version'=>'2.0.0'));
$active_plugins[]='fixture-plugin/plugin.php';
$store=new FakeStore();
$store->record(array('plugin_slug'=>'fixture-plugin','status'=>'success'));
$inspector=new WP_Plugin_Deploy_Plugin_Inspector($store,new FakeBackupList());
$status=$inspector->status('fixture-plugin');
assert_true($status['installed']===true,'status should report installed plugin');
assert_true($status['active']===true,'status should report active plugin');
assert_true(($status['version']??'')==='2.0.0','status should expose plugin version');
assert_true(isset($status['backups'][0]['id']),'status should include backup id');
assert_true(!isset($status['backups'][0]['path']),'status must not expose backup filesystem path');
echo "PASS status redaction\n";

rrmdir($fake_wp);
echo "PASS deployer integration smoke\n";
