<?php

/**
 * Plugin Name: CF7 Conditional Settings
 * Description: Lägger till villkorade inställningar för Contact Form 7 formulär.
 * Version: 1.0
 * Author: Max Trewhitt
 * Author URI: https://github.com/SpiZeak
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: cf7-conditional-settings
 * Requires PHP: 7.4
 * Requires at least: 5.7
 * Requires Plugins: advanced-custom-fields-pro
 * Domain Path: /languages
 */
// Exit if accessed directly
if (! defined('ABSPATH')) {
	exit;
}

class CF7ConditionalSettings
{
	const ACTIVATION_ERROR = '';

	public function __construct()
	{
		register_activation_hook(__FILE__, [$this, 'activate']);
		add_action('plugins_loaded', [$this, 'plugins_loaded']);
	}

	public function activate()
	{
		// Check if Contact Form 7 is activated
		if (!class_exists('WPCF7_ContactForm')) {
			set_transient(self::ACTIVATION_ERROR, __('CF7 Conditional Settings kräver att Contact Form 7 är aktiverat.', 'cf7-conditional-settings'));
		}

		// Check if Advanced Custom Fields Pro is activated and version is >= 6.3.8
		if (!class_exists('acf_pro') || version_compare(ACF_VERSION, '6.3.8', '<')) {
			set_transient(self::ACTIVATION_ERROR, __('CF7 Conditional Settings kräver Advanced Custom Fields Pro version 6.3.8 eller högre för att aktiveras.', 'cf7-conditional-settings'));
		}
	}

	public function plugins_loaded()
	{
		$has_to_be_deactivated = get_transient(self::ACTIVATION_ERROR);

		if (is_admin() && $has_to_be_deactivated) {
			add_action('admin_notices', [$this, 'display_warning_no_activation']);
			deactivate_plugins(plugin_basename(__FILE__));
		}

		// Register ACF fields and options page
		add_filter('acf/settings/load_json',  function ($paths) {
			$paths[] = plugin_dir_path(__FILE__) . 'acf-json';

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

		add_filter('pre_do_shortcode_tag', function ($output, $tag, $attr) {
			if ($tag !== 'contact-form-7') {
				return $output;
			}

			$conditional_forms = get_field('conditional_forms', 'option');
			$filtered_form = array_filter($conditional_forms, function ($form) use ($attr) {
				return $form['form'] === $attr['id'];
			})[0] ?? null;

			if (!$filtered_form) {
				return $output;
			}

			$now = new DateTime(current_time('mysql'));
			$start_date = new DateTime($filtered_form['start_datetime']);
			$end_date = new DateTime($filtered_form['end_datetime']);

			if ($filtered_form['is_shown_during_period'] === false) {
				$eject = !($now < $start_date || $now > $end_date);
			} else {
				$eject = $now < $start_date || $now > $end_date;
			}

			if ($eject) {
				return '';
			} else {
				return $output;
			}
		}, 10, 3);
	}

	public function display_warning_no_activation()
	{
		$activation_error = get_transient(self::ACTIVATION_ERROR);

		if ($activation_error) {
?>
			<div class="notice notice-error">
				<p><?= $activation_error ?></p>
			</div>
<?php
		}

		delete_transient(self::ACTIVATION_ERROR);
	}
}

new CF7ConditionalSettings();
