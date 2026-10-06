<?php
/** Isolated contract tests; these stub WordPress APIs, not a live installation. */
define( 'ABSPATH', __DIR__ );
define( 'HOUR_IN_SECONDS', 3600 );
$options = array(); $uploads = sys_get_temp_dir() . '/fontready-' . bin2hex( random_bytes( 8 ) );
$origin = 'https://fontready.com'; $capability = true; $fail_update = false; $styles = array(); $checks = 0;
class WP_Error { public $code; public $message; public $data; function __construct($code,$message,$data=array()) { $this->code=$code; $this->message=$message; $this->data=$data; } function get_error_code() { return $this->code; } }
class WP_REST_Response { public $data; public $status; function __construct($data,$status) { $this->data=$data; $this->status=$status; } }
class Request { private $payload; private $key; function __construct($payload,$key='') { $this->payload=$payload; $this->key=$key; } function get_body() { return json_encode($this->payload); } function get_json_params() { return $this->payload; } function get_header($name) { return $this->key; } }
function add_action(...$args) {} function add_filter(...$args) {} function register_deactivation_hook(...$args) {}
function get_option($name,$default=false) { global $options; return $options[$name] ?? $default; }
function add_option($name,$value,...$args) { global $options; if(isset($options[$name]))return false; $options[$name]=$value; return true; }
function update_option($name,$value,...$args) { global $options,$fail_update; if($fail_update)return false; $options[$name]=$value; return true; }
function delete_option($name) { global $options; unset($options[$name]); }
function is_wp_error($value) { return $value instanceof WP_Error; }
function user_can(...$args) { global $capability; return $capability; }
function get_http_origin() { global $origin; return $origin; }
function wp_upload_dir() { global $uploads; return array('error'=>false,'basedir'=>$uploads,'baseurl'=>'https://wordpress.example/uploads'); }
function wp_mkdir_p($directory) { return is_dir($directory) || mkdir($directory,0777,true); }
function wp_generate_uuid4() { return bin2hex(random_bytes(16)); }
function wp_delete_file($path) { if(is_file($path))unlink($path); }
function wp_json_encode($value,$options=0) { return json_encode($value,$options); }
function esc_url_raw($url) { return $url; }
function wp_style_is(...$args) { return false; }
function wp_register_style(...$args) {} function wp_enqueue_style(...$args) {}
function wp_add_inline_style($name,$css) { global $styles; $styles[$name]=$css; }
function check($condition,$label) { global $checks; $checks++; if(!$condition)throw new Exception('FAILED: '.$label); }
require __DIR__ . '/../fontready/fontready.php';
$payload = json_decode(file_get_contents($argv[1]),true);
$base = $payload['fonts'][0];
$valid = fontready_validate_fonts($payload);
check(!is_wp_error($valid), 'All four converted formats accepted');
$key = str_repeat('a',64);
$options[FontReady_Plugin::CONNECTION] = array('hash'=>hash('sha256',$key),'expires'=>time()+3600,'user'=>1);
check(FontReady_Plugin::authorize(new Request($payload,'Bearer '.$key)) === true, 'Valid key');
check(is_wp_error(FontReady_Plugin::authorize(new Request($payload,'Bearer '.str_repeat('b',64)))), 'Wrong key');
check(is_wp_error(FontReady_Plugin::authorize(new Request($payload,''))), 'Missing key');
$options[FontReady_Plugin::CONNECTION]['expires']=time()-1;
check(is_wp_error(FontReady_Plugin::authorize(new Request($payload,'Bearer '.$key))), 'Expired key');
$options[FontReady_Plugin::CONNECTION]['expires']=time()+3600;
$origin='https://evil.example';
check(is_wp_error(FontReady_Plugin::authorize(new Request($payload,'Bearer '.$key))), 'Untrusted origin');
$origin='https://fontready.com'; $capability=false;
check(is_wp_error(FontReady_Plugin::authorize(new Request($payload,'Bearer '.$key))), 'Revoked user capability');
$capability=true;
unset($options[FontReady_Plugin::CONNECTION]);
check(is_wp_error(FontReady_Plugin::authorize(new Request($payload,'Bearer '.$key))), 'Revoked connection');
foreach(array('family'=>'x\";}</style><script>', 'weight'=>'400;display:none', 'style'=>'bold', 'format'=>'svg', 'data'=>'not a font') as $field=>$value) {
    $bad=$base; $bad[$field]=$value;
    check(is_wp_error(fontready_validate_fonts(array('version'=>1,'fonts'=>array($bad)))), 'Reject '.$field);
}
check(is_wp_error(fontready_validate_fonts(array('version'=>1,'fonts'=>array($base,$base)))), 'Duplicate face rejected');
$bad=$base; $bad['weight']='900 100';
check(is_wp_error(fontready_validate_fonts(array('version'=>1,'fonts'=>array($bad)))), 'Reversed variable range');
$variable=$base; $variable['family']='Ọmọ Test'; $variable['weight']='100 900';
check(!is_wp_error(fontready_validate_fonts(array('version'=>1,'fonts'=>array($variable)))), 'Unicode family and variable range');
$bad=$base; $bad['data']=base64_encode(str_repeat('x',5*1024*1024+1));
check(is_wp_error(fontready_validate_fonts(array('version'=>1,'fonts'=>array($bad)))), 'Oversize rejected');
$bad=$base; $bad['data']=base64_encode('wOF2'.str_repeat("\0",8));
check(is_wp_error(fontready_validate_fonts(array('version'=>1,'fonts'=>array($bad)))), 'Truncated header rejected');
$response=FontReady_Plugin::import(new Request($payload));
check($response instanceof WP_REST_Response && $response->status===200, 'Import succeeds');
check(count(get_option(FontReady_Plugin::REGISTRY))===4, 'Four variants saved');
check(count(glob($uploads.'/fontready/*'))===4, 'Four font files');
$response=FontReady_Plugin::import(new Request($payload));
check($response instanceof WP_REST_Response && count(glob($uploads.'/fontready/*'))===4, 'Identical retry does not duplicate files');
check(!isset($options['fontready_import_lock']), 'Lock released');
$mapped=FontReady_Plugin::fonts(array('Arial'=>'system'));
check($mapped[$base['family']]==='fontready' && $mapped['Arial']==='system', 'Elementor font registration');
FontReady_Plugin::styles();
check(strpos($styles['fontready'],'font-display:swap')!==false && strpos($styles['fontready'],'https:')!==false, 'Local font CSS');
$new=$base; $new['family']='Another Family'; $fail_update=true;
$response=FontReady_Plugin::import(new Request(array('version'=>1,'fonts'=>array($new))));
check(is_wp_error($response), 'Database error returned');
check(count(glob($uploads.'/fontready/*'))===4 && count(get_option(FontReady_Plugin::REGISTRY))===4, 'Failed import rolls back files');
$fail_update=false;
$options['fontready_import_lock']=time();
check(is_wp_error(FontReady_Plugin::import(new Request($payload))), 'Concurrent change rejected');
unset($options['fontready_import_lock']);
foreach(glob($uploads.'/fontready/*') as $file)unlink($file);
rmdir($uploads.'/fontready'); rmdir($uploads);
echo "Passed $checks plugin contract checks.\n";
