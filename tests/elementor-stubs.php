<?php
/** Isolated native manager contract double, not a live Elementor installation. */
$native_posts=array();$native_meta=array();$native_next=100;
class NativeManager {
 const CPT='elementor_font'; const TAXONOMY='elementor_font_type'; const FONTS_DATA_OPTION_NAME='elementor_fonts_manager_fonts';
 function get_font_type_object($type){return new NativeCustomFonts();}
}
class NativeCustomFonts {
 const FONT_META_KEY='elementor_font_files'; const FONT_FACE_META_KEY='elementor_font_face';
 function save_meta($id,$data){update_post_meta($id,self::FONT_META_KEY,$data['font_face']);update_post_meta($id,self::FONT_FACE_META_KEY,'native-css');}
}
class NativeModule {static function instance(){return new self;}function get_component($name){return new NativeManager();}}
class_alias('NativeModule','ElementorPro\\Modules\\AssetsManager\\Module');
class_alias('NativeManager','ElementorPro\\Modules\\AssetsManager\\AssetTypes\\Fonts_Manager');
function post_type_exists($type){return $type==='elementor_font';}
function get_posts($query){global $native_posts;return array_values(array_filter($native_posts,function($p)use($query){return $p->post_type===$query['post_type']&&$p->post_title===$query['title'];}));}
function wp_insert_post($data,$error=false){global $native_posts,$native_next;$id=++$native_next;$native_posts[$id]=(object)array_merge($data,array('ID'=>$id));return $id;}
function wp_update_post($data,$error=false){global $native_posts;foreach($data as $k=>$v)$native_posts[$data['ID']]->$k=$v;return $data['ID'];}
function get_post_status($id){global $native_posts;return $native_posts[$id]->post_status??false;}
function update_post_meta($id,$key,$data){global $native_meta;$native_meta[$id][$key]=array($data);return true;}
function get_post_meta($id,$key='',$single=false){global $native_meta;if(!$key)return $native_meta[$id]??array();return $single?($native_meta[$id][$key][0]??''):($native_meta[$id][$key]??array());}
function delete_post_meta($id,$key){global $native_meta;unset($native_meta[$id][$key]);}
function add_post_meta($id,$key,$value){global $native_meta;$native_meta[$id][$key][]=$value;}
function maybe_unserialize($data){return $data;}
function wp_set_object_terms($id,$term,$taxonomy){return array(1);}
function wp_insert_attachment($data,$path,$parent,$error){$data['post_type']='attachment';$id=wp_insert_post($data);update_post_meta($id,'_wp_attached_file',$path);return $id;}
function wp_delete_attachment($id,$force){wp_delete_post($id,$force);return true;}
function wp_delete_post($id,$force){global $native_posts,$native_meta;unset($native_posts[$id],$native_meta[$id]);return true;}
