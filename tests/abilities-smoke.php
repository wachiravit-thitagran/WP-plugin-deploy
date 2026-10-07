<?php
$actions=[]; $cats=[]; $abilities=[];
function add_action($hook,$cb){ global $actions; $actions[$hook][]=$cb; }
function wp_register_ability_category($slug,$args){ global $cats; $cats[$slug]=$args; return (object)$args; }
function wp_register_ability($name,$args){ global $abilities; $abilities[$name]=$args; return (object)$args; }
function __($s,$d=null){ return $s; }
function current_user_can($cap){ return true; }
function plugin_dir_path($f){ return dirname($f).'/'; }
function is_wp_error($v){ return $v instanceof WP_Error; }
class WP_Error { public function __construct($c='',$m=''){} }
define('ABSPATH', __DIR__.'/fake-wp/');
define('WP_CONTENT_DIR', __DIR__.'/content');
define('WP_PLUGIN_DIR', __DIR__.'/plugins');
require __DIR__ . '/../wp-plugin-deploy.php';
foreach ($actions['wp_abilities_api_categories_init'] ?? [] as $cb) { call_user_func($cb); }
foreach ($actions['wp_abilities_api_init'] ?? [] as $cb) { call_user_func($cb); }
function a($cond,$m){ if(!$cond){fwrite(STDERR,"FAIL: $m\n"); exit(1);} }
global $cats,$abilities;
a(isset($cats['plugin-deployment']), 'category registered');
a(isset($abilities['wordpress/plugin-deploy']), 'deploy ability registered');
a(isset($abilities['wordpress/plugin-rollback']), 'rollback ability registered');
a(isset($abilities['wordpress/plugin-deploy-status']), 'status ability registered');
a(($abilities['wordpress/plugin-deploy-status']['meta']['annotations']['readonly'] ?? null) === true, 'status readonly annotation');
a(($abilities['wordpress/plugin-deploy']['meta']['public'] ?? null) === true, 'deploy public for MCP');
echo "PASS abilities\n";
