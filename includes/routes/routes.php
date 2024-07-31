<?php

/**
 * Requeriment:
 *
 * composer require hexbit/router
 */

namespace WicketAcc;

use Hexbit\Router\WordPress\Router;

// No direct access
defined('ABSPATH') || exit;

if (is_admin()) {
	return;
}

// ACC page slug
$acc_slug = WICKET_ACC_SLUG;

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

// TODO: make it overridable
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

Router::group($acc_slug, function ($group) {
	$group->get('', function () {
		global $acc_template_dir;

		include_once $acc_template_dir . 'account-centre.php';
	});

	$group->get('edit-profile/', function () {
		global $acc_template_dir;

		include_once $acc_template_dir . 'edit-profile.php';
	});

	die();
});
