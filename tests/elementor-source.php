<?php
/** Optional integration contract: load supplied vendor classes, not the plugin bootstrap. */
namespace ElementorPro\Base {
    class Module_Base {
        public static $test_instance;
        public static function instance() { return self::$test_instance; }
        public function get_component($name) { return null; }
    }
    trait Editor_One_Trait {}
}
namespace {
    $vendor = rtrim(getenv('ELEMENTOR_PRO_SOURCE'), '/');
    require $vendor.'/modules/assets-manager/classes/assets-base.php';
    require $vendor.'/modules/assets-manager/classes/font-base.php';
    require $vendor.'/modules/assets-manager/asset-types/fonts/custom-fonts.php';
    require $vendor.'/modules/assets-manager/asset-types/fonts-manager.php';
    require $vendor.'/modules/assets-manager/module.php';
    $type = (new ReflectionClass('ElementorPro\\Modules\\AssetsManager\\AssetTypes\\Fonts\\Custom_Fonts'))->newInstanceWithoutConstructor();
    $manager = (new ReflectionClass('ElementorPro\\Modules\\AssetsManager\\AssetTypes\\Fonts_Manager'))->newInstanceWithoutConstructor();
    $manager->add_font_type('custom', $type);
    $module = (new ReflectionClass('ElementorPro\\Modules\\AssetsManager\\Module'))->newInstanceWithoutConstructor();
    $module->add_asset_manager('font', $manager);
    \ElementorPro\Base\Module_Base::$test_instance = $module;
    function get_the_title($id) { global $native_posts; return $native_posts[$id]->post_title; }
    function apply_filters($name, $value, ...$args) { return $value; }
}
