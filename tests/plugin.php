<?php
/** Isolated contract tests; these stub WordPress APIs, not a live installation. */
define( 'ABSPATH', __DIR__ );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'ELEMENTOR_PRO_VERSION', 'test' );
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
require __DIR__ . '/elementor-stubs.php';
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
$native_payload=$payload;$native_payload['fonts']=array_slice($payload['fonts'],0,3);
$response=FontReady_Plugin::import(new Request($native_payload));
check($response instanceof WP_REST_Response && $response->status===200, 'Import succeeds');
check(count(get_option(FontReady_Plugin::REGISTRY))===3, 'Three native supported variants saved');
$native_id=array_values(get_option(FontReady_Plugin::REGISTRY))[0]['elementor_post_id'];
check(get_post_status($native_id)==='publish','Native font record published');
check(count(get_post_meta($native_id,NativeCustomFonts::FONT_META_KEY,true))===3,'Native font variants saved through Elementor API');
check(count(glob($uploads.'/fontready/*'))===3, 'Four font files');
$response=FontReady_Plugin::import(new Request($native_payload));
check($response instanceof WP_REST_Response && count(glob($uploads.'/fontready/*'))===3, 'Identical retry does not duplicate files');
check(!isset($options['fontready_import_lock']), 'Lock released');
$mapped=FontReady_Plugin::fonts(array('Arial'=>'system'));
check($mapped[$base['family']]==='custom' && $mapped['Arial']==='system', 'Elementor font registration');
FontReady_Plugin::styles();
check(strpos($styles['fontready'],'font-display:swap')!==false && strpos($styles['fontready'],'https:')!==false, 'Local font CSS');
$new=$base; $new['family']='Another Family'; $fail_update=true;
$response=FontReady_Plugin::import(new Request(array('version'=>1,'fonts'=>array($new))));
check(is_wp_error($response), 'Database error returned');
check(count(glob($uploads.'/fontready/*'))===3 && count(get_option(FontReady_Plugin::REGISTRY))===3, 'Failed import rolls back files');
$fail_update=false;
$options['fontready_import_lock']=time();
check(is_wp_error(FontReady_Plugin::import(new Request($payload))), 'Concurrent change rejected');
unset($options['fontready_import_lock']);
$existing_id=wp_insert_post(array('post_type'=>'elementor_font','post_title'=>'Existing Manual Font','post_status'=>'publish'));
$collision=$base;$collision['family']='Existing Manual Font';
check(is_wp_error(FontReady_Plugin::import(new Request(array('version'=>1,'fonts'=>array($collision))))),'Unowned native family collision rejected');
check(get_post_meta($existing_id,'_fontready_owned',true)==='','Manual native font remains untouched');
check(count(glob($uploads.'/fontready/*'))===3,'Collision does not leave font files');
$native_before=count($native_posts);
check(is_wp_error(FontReady_Plugin::import(new Request(array('version'=>1,'fonts'=>array($payload['fonts'][3]))))),'Direct OTF rejected with conversion guidance');
check(count($native_posts)===$native_before&&count(glob($uploads.'/fontready/*'))===3,'Unsupported native import rolls back records and files');
foreach(glob($uploads.'/fontready/*') as $file)unlink($file);
rmdir($uploads.'/fontready'); rmdir($uploads);
echo "Passed $checks plugin contract checks.\n";

// Administrator redirect and dashboard contracts.
define('DAY_IN_SECONDS',86400);
class RedirectResult extends Exception { public $url; function __construct($url){$this->url=$url;} }
$nonce_ok=true;
function current_user_can(...$args){return user_can(...$args);}
function check_admin_referer($action){global $nonce_ok;if(!$nonce_ok)throw new Exception('Invalid nonce');}
function wp_unslash($value){return $value;}
function sanitize_key($value){return $value;}
function sanitize_text_field($value){return $value;}
function wp_parse_url($url,$component){return parse_url($url,$component);}
function rest_url($route=''){return 'https://wordpress.example/wp-json/'.$route;}
function home_url($path=''){return 'https://wordpress.example'.$path;}
function admin_url($path=''){return 'https://wordpress.example/wp-admin/'.$path;}
function get_current_user_id(){return 1;}
function wp_die($message){throw new Exception($message);}
function wp_redirect($url){throw new RedirectResult($url);}
function wp_safe_redirect($url){throw new RedirectResult($url);}
function esc_html__($value,$domain){return $value;}
function esc_html($value){return htmlspecialchars((string)$value,ENT_QUOTES);}
function esc_attr($value){return esc_html($value);}
function wp_nonce_field($action){echo '<input name="_wpnonce" value="fixture">';}
function disabled($condition){if($condition)echo 'disabled';}
$_POST=array('fontready_action'=>'connect','fontready_state'=>str_repeat('b',64),'fontready_confirm'=>'1');
$nonce_ok=false;
try{FontReady_Plugin::connect();check(false,'Nonce required');}catch(Exception $error){check($error->getMessage()==='Invalid nonce','Nonce required');}
$nonce_ok=true;$_POST['fontready_state']='https://evil.example/';
try{FontReady_Plugin::connect();check(false,'State validation');}catch(Exception $error){check(!($error instanceof RedirectResult),'Invalid state rejected');}
$_POST['fontready_state']=str_repeat('b',64);unset($_POST['fontready_confirm']);
try{FontReady_Plugin::connect();check(false,'Confirmation required');}catch(Exception $error){check(!($error instanceof RedirectResult),'Confirmation required');}
$_POST['fontready_confirm']='1';
try{FontReady_Plugin::connect();check(false,'Redirect expected');}catch(RedirectResult $redirect){
    check(strpos($redirect->url,'https://fontready.com/font-to-elementor-pro/#fontready=')===0,'Fixed callback origin');
    $details=json_decode(rawurldecode(substr($redirect->url,strpos($redirect->url,'#fontready=')+11)),true);
    check(preg_match('/^[a-f0-9]{64}$/',$details['key'])===1,'Automatic opaque credential');
    $stored=get_option(FontReady_Plugin::CONNECTION);
    check($stored['hash']===hash('sha256',$details['key'])&&!isset($stored['key']),'Only hash stored in WordPress');
    check($stored['expires']>time()+6*86400,'Seamless seven-day authorization');
}
$_POST=array();$_GET=array('fontready_connect'=>str_repeat('b',64));
ob_start();FontReady_Plugin::page();$html=ob_get_clean();
check(strpos($html,'Fonts installed')!==false&&strpos($html,'Website connection')!==false,'Dashboard connection and count');
check(strpos($html,$details['key'])===false&&strpos($html,'Generate connection key')===false,'No visible credentials');
check(strpos($html,'Elementor Pro is installed')!==false&&strpos($html,'wordpress.example')!==false,'Domain and Pro confirmation');
$_POST=array('fontready_action'=>'disconnect');
try{FontReady_Plugin::connect();}catch(RedirectResult $redirect){check(!get_option(FontReady_Plugin::CONNECTION),'Disconnect revokes authorization');}
echo "Passed administrator redirect and dashboard contracts; total $checks checks.\n";
