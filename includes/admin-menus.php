<?php
/**
 * Admin menu registration for LearnDash Evidence.
 *
 * Registers all admin subpages: hidden report page, student tracking,
 * and course dates migration tool.
 *
 * @package learndash-evidence
 * @since   1.6.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'lde_register_submenus' );

/**
 * Registers admin submenu pages under LearnDash LMS.
 *
 * @since 1.3.0
 *
 * @return void
 */
function lde_register_submenus() {
	// Hidden subpage for evidence report.
	add_submenu_page(
		null,
		esc_html__( 'LearnDash Evidence', 'learndash-evidence' ),
		esc_html__( 'LearnDash Evidence', 'learndash-evidence' ),
		'edit_users',
		'learndash-evidence',
		'lde_user_courses_list'
	);

	// Visible subpage for student tracking under LearnDash.
	add_submenu_page(
		'learndash-lms',
		esc_html__( 'Student Tracking', 'learndash-evidence' ),
		esc_html__( 'Student Tracking', 'learndash-evidence' ),
		'edit_users',
		'student-list',
		function () {
			include plugin_dir_path( dirname( __FILE__ ) ) . 'student-list.php';
		}
	);

	// Migration page for fixing course access dates.
	add_submenu_page(
		'learndash-lms',
		esc_html__( 'Fix Course Dates', 'learndash-evidence' ),
		esc_html__( 'Fix Course Dates', 'learndash-evidence' ),
		'manage_options',
		'fix-course-dates',
		'lde_fix_dates_page'
	);

	// Diagnostic page for LD compatibility checks.
	add_submenu_page(
		'learndash-lms',
		esc_html__( 'LDE Diagnostic', 'learndash-evidence' ),
		esc_html__( 'LDE Diagnostic', 'learndash-evidence' ),
		'manage_options',
		'lde-diagnostic',
		'lde_diagnostic_page'
	);
}

/**
 * Displays a list of enrolled courses for a given user.
 *
 * Used as the callback for the hidden evidence report landing page.
 *
 * @since 1.3.0
 *
 * @return void
 */
function lde_user_courses_list() {
	$user_id = isset( $_GET['user_id'] ) ? intval( $_GET['user_id'] ) : get_current_user_id();
	$user    = get_userdata( $user_id );
	$courses = learndash_user_get_enrolled_courses( $user_id );

	echo '<div class="wrap"><h1>' . esc_html( $user->display_name ) . '</h1><ul>';
	foreach ( $courses as $course_id ) {
		$title = get_the_title( $course_id );
		if ( ! $title ) {
			continue;
		}
		$url = admin_url( 'users.php?page=learndash-evidence&ld_course_report=' . $course_id . '&user_id=' . $user_id );
		echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a></li>';
	}
	echo '</ul></div>';
}
