<?php
/**
 * User profile integration for LearnDash Evidence.
 *
 * Adds a quick-access block at the top of user profile pages
 * linking to each enrolled course's evidence report.
 *
 * @package learndash-evidence
 * @since   1.6.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Displays a LearnDash Evidence quick-access block on user profile pages.
 *
 * @since 1.3.0
 *
 * @return void
 */
function lde_profile_block_top() {
	global $pagenow;
	if ( ! current_user_can( 'edit_users' ) || ! in_array( $pagenow, array( 'user-edit.php', 'profile.php' ), true ) ) {
		return;
	}

	$user_id = isset( $_GET['user_id'] ) ? intval( $_GET['user_id'] ) : get_current_user_id();
	$courses = learndash_user_get_enrolled_courses( $user_id );
	if ( ! $courses ) {
		return;
	}

	echo '<div class="notice"><h2>' . esc_html__( 'LearnDash Evidence', 'learndash-evidence' ) . '</h2><ul>';
	foreach ( $courses as $course_id ) {
		$title = get_the_title( $course_id );
		$url   = admin_url( 'users.php?page=learndash-evidence&ld_course_report=' . $course_id . '&user_id=' . $user_id );
		echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a></li>';
	}
	echo '</ul></div>';
}
add_action( 'all_admin_notices', 'lde_profile_block_top' );
