<?php

namespace WicketAcc;

use Hexbit\Router\WordPress\Router;
use Hexbit\Router\WordPress\VirtualPage;

// No direct access
defined('ABSPATH') || exit;

if (is_admin()) {
	return;
}

// ACC page slug
//$acc_slug = WACC()->get_acc_page_slug();
$acc_slug = 'account-centre';

// ACC page slug map for main supported languages
$acc_slug_map_languages = [
	'account-centre' => [
		'en' => 'account-centre',
		'fr' => 'centre-de-compte',
		'es' => 'centro-de-cuenta',
	],
	'edit-profile' => [
		'en' => 'edit-profile',
		'fr' => 'editer-mon-profil',
		'es' => 'editar-mi-perfil',
	],
];

// WPML compatibility
// Get current language code from WPML
$acc_lang = 'en';

if (defined('ICL_SITEPRESS_VERSION')) {
	$acc_lang = apply_filters('wpml_current_language', NULL);
}

// If language is not 'en', change the slug
if ($acc_lang != 'en') {
	$acc_slug = $acc_slug_map_languages[$acc_slug][$acc_lang];
}

$acc_template_dir = WICKET_ACC_PLUGIN_TEMPLATE_PATH . 'account-centre/';

// Init router
add_action("init", function () {
	Router::init();
});

/**
 * Routes for ACC
 *
 * See: https://github.com/smarteist/wordpress-router
 */
/*Router::get($acc_slug, function ($request) {
	global $acc_template_dir;

	include_once $acc_template_dir . 'account-center.php';
	die();
});*/

Router::group($acc_slug, function ($group) {
	global $acc_slug, $acc_template_dir;

	$group->get('', function () {
		global $acc_slug, $acc_template_dir;

		include_once $acc_template_dir . 'account-centre.php';
	});

	$group->get('edit-profile/', function () {
		global $acc_slug, $acc_template_dir;

		include_once $acc_template_dir . 'edit-profile.php';
	});
});

/*$AccPage_Index = new VirtualPage($acc_slug, __('Account Centre', 'wicket-acc'), $acc_template_dir);

ray($acc_slug);
ray($AccPage_Index);
ray($acc_slug . '/');

Router::virtualPage($acc_slug . '/', $AccPage_Index);*/
