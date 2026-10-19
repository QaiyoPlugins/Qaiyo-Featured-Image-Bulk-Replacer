<?php
/**
 * Plugin Name: Qaiyo Featured Image Bulk Replacer
 * Plugin URI: https://qaiyo-plugins.com/qaiyo-featured-image-bulk-replacer/
 * Description: Bulk-replace the featured image of published posts with an image that already exists in the Media Library — one image or a random set, filtered by date, category, tag, author or taxonomy. Featured image column in post lists, broken-image cleanup, preview, batch processing and one-click restore.
 * Version: 1.3.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Qaiyo
 * Author URI: https://qaiyo-plugins.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: qaiyo-featured-image-bulk-replacer
 * Domain Path: /languages
 *
 * @package qaiyo-featured-image-bulk-replacer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'QFIBR_VERSION', '1.3.0' );
define( 'QFIBR_PLUGIN_FILE', __FILE__ );
define( 'QFIBR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'QFIBR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'QFIBR_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load the plugin's classes.
 *
 * Ordered so that a class is always defined before the one that refers to it at
 * declaration time (Qfibr_Plugin mirrors constants from the others, so it comes
 * last).
 */
foreach ( array(
	'class-qfibr-cast.php',
	'class-qfibr-kses.php',
	'class-qfibr-view.php',
	'class-qfibr-criteria.php',
	'class-qfibr-replacer.php',
	'class-qfibr-attachment.php',
	'class-qfibr-query.php',
	'class-qfibr-image-picker.php',
	'class-qfibr-orphans.php',
	'class-qfibr-job-store.php',
	'class-qfibr-runner.php',
	'class-qfibr-preview.php',
	'class-qfibr-ajax.php',
	'class-qfibr-pro-teaser.php',
	'class-qfibr-admin-page.php',
	'class-qfibr-admin-assets.php',
	'class-qfibr-list-column.php',
	'class-qfibr-usage.php',
	'class-qfibr-plugin.php',
	'class-qfibr-brand-menu.php',
	'class-qfibr-more-plugins.php',
) as $qfibr_class_file ) {
	require_once QFIBR_PLUGIN_DIR . 'includes/' . $qfibr_class_file;
}
unset( $qfibr_class_file );

/**
 * Cache-busting version for a bundled asset: plugin version plus file mtime,
 * so an edited CSS/JS file is never served stale from the browser cache.
 *
 * @param string $relative Path relative to the plugin folder.
 * @return string
 */
function qfibr_asset_version( $relative ) {
	$path  = QFIBR_PLUGIN_DIR . $relative;
	$mtime = file_exists( $path ) ? filemtime( $path ) : false;
	return QFIBR_VERSION . ( false !== $mtime ? '.' . $mtime : '' );
}

Qfibr_Brand_Menu::init();
Qfibr_More_Plugins::register( array( 'menu_parent' => 'qaiyo-featured-image-bulk-replacer' ) );

/**
 * Load translations following the Qaiyo i18n standard (no load_plugin_textdomain()).
 *
 * @return void
 */
function qfibr_load_translations() {
	$locale = determine_locale();

	$locale_map = array(
		'de_CH'          => 'de_DE',
		'de_AT'          => 'de_DE',
		'de_DE_formal'   => 'de_DE',
		'de_CH_informal' => 'de_DE',
		'fr_BE'          => 'fr_FR',
		'fr_CA'          => 'fr_FR',
		'fr_CH'          => 'fr_FR',
		'es_MX'          => 'es_ES',
		'es_AR'          => 'es_ES',
		'es_CO'          => 'es_ES',
		'es_VE'          => 'es_ES',
		'es_CL'          => 'es_ES',
		'es_PE'          => 'es_ES',
		'pt_BR'          => 'pt_PT',
		'pt_AO'          => 'pt_PT',
		'pt_MZ'          => 'pt_PT',
		'ja_JP'          => 'ja',
		'ru_UA'          => 'ru_RU',
		'ru_BY'          => 'ru_RU',
		'tr_CY'          => 'tr_TR',
	);

	if ( isset( $locale_map[ $locale ] ) ) {
		$locale = $locale_map[ $locale ];
	}

	$supported = array( 'en_US', 'hu_HU', 'de_DE', 'fr_FR', 'es_ES', 'ja', 'pt_PT', 'it_IT', 'ru_RU', 'tr_TR', 'pl_PL' );
	if ( ! in_array( $locale, $supported, true ) || 'en_US' === $locale ) {
		return;
	}

	$mofile = QFIBR_PLUGIN_DIR . 'languages/qaiyo-featured-image-bulk-replacer-' . $locale . '.mo';
	if ( file_exists( $mofile ) ) {
		load_textdomain( 'qaiyo-featured-image-bulk-replacer', $mofile );
	}
}
add_action( 'init', 'qfibr_load_translations', 1 );

Qfibr_Plugin::register();
