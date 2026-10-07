<?php
function assert_true($condition, $message) { if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } }
function is_wp_error($v) { return $v instanceof WP_Error; }
class WP_Error { public $code; public $message; public function __construct($code='', $message=''){ $this->code=$code; $this->message=$message; } public function get_error_code(){ return $this->code; } }
function sanitize_key($key){ return strtolower(preg_replace('/[^a-z0-9_\-]/','',$key)); }
function esc_url_raw($url){ return $url; }
function wp_parse_url($url){ return parse_url($url); }
function wp_http_validate_url($url){ $p=parse_url($url); return is_array($p) && isset($p['scheme'],$p['host']) && $p['scheme']==='https'; }

require __DIR__ . '/../includes/class-package-resolver.php';
require __DIR__ . '/../includes/class-package-validator.php';

$r = new WP_Plugin_Deploy_Package_Resolver();
$out = $r->resolve(['source'=>'https://github.com/acme/example-plugin','ref'=>'v1.2.3','ref_type'=>'tag']);
assert_true(!is_wp_error($out), 'GitHub tag source should resolve');
assert_true($out['download_url']==='https://api.github.com/repos/acme/example-plugin/zipball/v1.2.3', 'GitHub tag should use API zipball URL');
assert_true($out['source_type']==='github', 'GitHub source type');
assert_true($out['plugin_slug_hint']==='example-plugin', 'GitHub repo name should provide stable slug hint');

$bad = $r->resolve(['source'=>'http://example.com/plugin.zip']);
assert_true(is_wp_error($bad) && $bad->get_error_code()==='invalid_source', 'HTTP source must be rejected');

assert_true(WP_Plugin_Deploy_Package_Validator::is_safe_archive_path('plugin/file.php'), 'normal archive path should be safe');
assert_true(!WP_Plugin_Deploy_Package_Validator::is_safe_archive_path('../evil.php'), 'traversal must be rejected');
assert_true(!WP_Plugin_Deploy_Package_Validator::is_safe_archive_path('/etc/passwd'), 'absolute Unix path must be rejected');
assert_true(!WP_Plugin_Deploy_Package_Validator::is_safe_archive_path('C:\\evil.php'), 'absolute Windows path must be rejected');
assert_true(WP_Plugin_Deploy_Package_Validator::is_valid_slug('my-plugin'), 'normal plugin slug valid');
assert_true(!WP_Plugin_Deploy_Package_Validator::is_valid_slug('../plugin'), 'path-like slug invalid');

echo "PASS smoke\n";
