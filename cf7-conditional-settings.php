<?php

/**
 * Plugin Name: CF7 Conditional Settings
 * Description: Adds conditional settings to CF7 under CF7 menu
 * Version: 1.0
 * Author: Max Trewhitt
 * Author URI: https://github.com/SpiZeak
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: cf7-conditional-settings
 * Requires PHP: 7.4
 * Requires at least: 5.7
 * Requires Plugins: advanced-custom-fields-pro
 */

// Check if Contact Form 7 and Advanced Custom Fields Pro is activated
if (!class_exists('WPCF7_ContactForm') || !class_exists('acf_pro')) {
	return;
}

// Register ACF fields and options page
add_filter('acf/settings/load_json',  function ($paths) {
	$paths[] = plugin_dir_url(__DIR__) . '/acf-json';

	return $paths;
});

// Dynamically populate with cf7
add_filter('acf/load_field/key=field_67099a7e3cd3f', function ($field) {
	$forms = get_posts([
		'post_type' => 'wpcf7_contact_form',
		'numberposts' => -1
	]);

	$field['choices'] = array_reduce($forms, function ($result, $form) {
		$wpcf7 = WPCF7_ContactForm::get_instance($form->ID);
		$hash = $wpcf7->hash();
		$result[$hash] = $form->post_title;

		return $result;
	}, []);

	return $field;
});
