<?php
function fail_native($message){ fwrite(STDERR,"FAIL: $message\n"); exit(1); }
function assert_native($condition,$message){ if(!$condition) fail_native($message); }

$file=__DIR__.'/../includes/class-native-upgrader.php';
assert_native(file_exists($file),'native upgrader adapter must exist');

class WP_Error {
    private $code; private $message;
    public function __construct($code='',$message=''){ $this->code=$code; $this->message=$message; }
    public function get_error_code(){ return $this->code; }
    public function get_error_message(){ return $this->message; }
}
function is_wp_error($v){ return $v instanceof WP_Error; }
function wp_clean_plugins_cache($clear=false){}
function activate_plugin($file){ global $activated_plugin; $activated_plugin=$file; return null; }
function is_plugin_active($file){ global $activated_plugin; return $activated_plugin===$file; }

class WP_Upgrader_Skin {
    public function __construct($args=array()){}
    public function feedback($feedback, ...$args){}
}
class Plugin_Upgrader {
    public static $last_package=null;
    public static $last_args=null;
    public $result=array('destination_name'=>'fixture-plugin','destination'=>'/tmp/fixture-plugin');
    public function __construct($skin=null){}
    public function install($package,$args=array()){
        self::$last_package=$package;
        self::$last_args=$args;
        return true;
    }
    public function plugin_info(){ return 'fixture-plugin/plugin.php'; }
}

require $file;

$adapter=new WP_Plugin_Deploy_Native_Upgrader();
$result=$adapter->install_or_update(
    'https://example.com/fixture-plugin.zip',
    'fixture-plugin',
    true
);

assert_native(!is_wp_error($result),'native upgrader should succeed when Plugin_Upgrader succeeds');
assert_native(Plugin_Upgrader::$last_package==='https://example.com/fixture-plugin.zip','package URL must be passed to Plugin_Upgrader');
assert_native((Plugin_Upgrader::$last_args['overwrite_package']??false)===true,'native flow must enable overwrite_package for upload-style updates');
assert_native(($result['plugin_file']??'')==='fixture-plugin/plugin.php','native upgrader should report installed plugin file');
assert_native(($result['active']??false)===true,'native upgrader should activate when requested');

echo "PASS native upgrader\n";
