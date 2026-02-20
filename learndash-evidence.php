<?php
/**
 * Plugin Name: LearnDash Evidence
 * Plugin URI:  https://flabernardez.com/learndash-evidence
 * Description: Adds a tracking section in LearnDash to monitor user progress and quiz scores, prepared for screencapture or print.
 * Version:     1.7.1
 * Author:      Flavia Bernardez Rodriguez
 * Author URI:  https://flabernardez.com
 * License:     GPL-2.0-or-later
 * Text Domain: learndash-evidence
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin base path constant.
 *
 * @since 1.6.0
 */
define( 'LDE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin version constant.
 *
 * @since 1.6.0
 */
define( 'LDE_VERSION', '1.7.1' );

// Enable excerpt support for LearnDash Topics.
add_action( 'init', function () {
	add_post_type_support( 'sfwd-topic', 'excerpt' );
} );

// Load modular includes.
require_once LDE_PLUGIN_DIR . 'includes/admin-menus.php';
require_once LDE_PLUGIN_DIR . 'includes/report-styles.php';
require_once LDE_PLUGIN_DIR . 'includes/report.php';
require_once LDE_PLUGIN_DIR . 'includes/autocomplete.php';
require_once LDE_PLUGIN_DIR . 'includes/date-fix.php';
require_once LDE_PLUGIN_DIR . 'includes/profile.php';
require_once LDE_PLUGIN_DIR . 'includes/diagnostic.php';
