<?php
/**
 * Course access date correction for LearnDash Evidence.
 *
 * Fixes the bug where course access dates show as January 1, 1970
 * by setting them to the user registration timestamp instead.
 * Includes a migration admin page and proactive hooks.
 *
 * @package learndash-evidence
 * @since   1.5.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the admin page for previewing and fixing 1970 course dates.
 *
 * @since 1.5.0
 *
 * @return void
 */
function lde_fix_dates_page() {
	if ( isset( $_POST['fix_dates'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'fix_course_dates' ) ) {
		$fixed_count = lde_fix_1970_dates();
		echo '<div class="notice notice-success"><p>' . sprintf( esc_html__( 'Fixed %d course access dates.', 'learndash-evidence' ), $fixed_count ) . '</p></div>';
	}

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__( 'Fix Course Access Dates', 'learndash-evidence' ) . '</h1>';
	echo '<p>' . esc_html__( 'This tool will fix course access dates that show as January 1, 1970, setting them to the user registration date instead.', 'learndash-evidence' ) . '</p>';

	$affected_users = lde_get_1970_users();
	if ( ! empty( $affected_users ) ) {
		echo '<h3>' . esc_html__( 'Users with incorrect dates:', 'learndash-evidence' ) . '</h3>';
		echo '<table class="wp-list-table widefat striped">';
		echo '<thead><tr><th>' . esc_html__( 'User', 'learndash-evidence' ) . '</th><th>' . esc_html__( 'Registration Date', 'learndash-evidence' ) . '</th><th>' . esc_html__( 'Affected Courses', 'learndash-evidence' ) . '</th></tr></thead>';
		echo '<tbody>';
		foreach ( $affected_users as $user_data ) {
			$user = get_userdata( $user_data['user_id'] );
			echo '<tr>';
			echo '<td>' . esc_html( $user->display_name ) . ' (' . esc_html( $user->user_email ) . ')</td>';
			echo '<td>' . esc_html( date_i18n( 'Y-m-d H:i:s', strtotime( $user->user_registered ) ) ) . '</td>';
			echo '<td>' . esc_html( implode( ', ', array_map( 'get_the_title', $user_data['courses'] ) ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		echo '<form method="post">';
		wp_nonce_field( 'fix_course_dates' );
		echo '<p><input type="submit" name="fix_dates" class="button-primary" value="' . esc_attr__( 'Fix All Dates', 'learndash-evidence' ) . '"></p>';
		echo '</form>';
	} else {
		echo '<div class="notice notice-info"><p>' . esc_html__( 'No users found with incorrect course access dates.', 'learndash-evidence' ) . '</p></div>';
	}
	echo '</div>';
}

/**
 * Retrieves users whose course access dates are invalid (1970).
 *
 * @since 1.5.0
 *
 * @return array List of arrays with 'user_id' and 'courses' keys.
 */
function lde_get_1970_users() {
	global $wpdb;

	$results = $wpdb->get_results( $wpdb->prepare(
		"SELECT user_id, meta_key, meta_value
		FROM {$wpdb->usermeta}
		WHERE meta_key LIKE %s
		AND (meta_value = '0' OR meta_value = 0 OR meta_value = '' OR meta_value <= 86400)",
		'course_%_access_from'
	) );

	$affected_users = array();
	foreach ( $results as $row ) {
		if ( preg_match( '/course_(\d+)_access_from/', $row->meta_key, $matches ) ) {
			$course_id = intval( $matches[1] );
			if ( ! isset( $affected_users[ $row->user_id ] ) ) {
				$affected_users[ $row->user_id ] = array(
					'user_id' => $row->user_id,
					'courses' => array(),
				);
			}
			$affected_users[ $row->user_id ]['courses'][] = $course_id;
		}
	}

	return array_values( $affected_users );
}

/**
 * Fixes all invalid 1970 course access dates in the database.
 *
 * @since 1.5.0
 *
 * @return int Number of records fixed.
 */
function lde_fix_1970_dates() {
	$affected_users = lde_get_1970_users();
	$fixed_count    = 0;

	foreach ( $affected_users as $user_data ) {
		$user = get_userdata( $user_data['user_id'] );
		if ( ! $user ) {
			continue;
		}

		$registration_timestamp = strtotime( $user->user_registered );

		foreach ( $user_data['courses'] as $course_id ) {
			$meta_key = 'course_' . $course_id . '_access_from';
			update_user_meta( $user_data['user_id'], $meta_key, $registration_timestamp );
			$fixed_count++;
		}
	}

	return $fixed_count;
}

/**
 * Sets course access date to registration date when a new user is created.
 *
 * @since 1.5.0
 *
 * @param int $user_id The newly registered user ID.
 * @return void
 */
function lde_set_access_date_on_register( $user_id ) {
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return;
	}

	$registration_timestamp = strtotime( $user->user_registered );
	$courses                = learndash_user_get_enrolled_courses( $user_id );

	foreach ( $courses as $course_id ) {
		$meta_key = 'course_' . $course_id . '_access_from';
		$current  = get_user_meta( $user_id, $meta_key, true );

		if ( empty( $current ) || intval( $current ) <= 86400 ) {
			update_user_meta( $user_id, $meta_key, $registration_timestamp );
		}
	}
}
add_action( 'user_register', 'lde_set_access_date_on_register', 10, 1 );

/**
 * Corrects course access date when user meta is added or updated.
 *
 * @since 1.5.0
 *
 * @param int    $meta_id    The meta ID.
 * @param int    $user_id    The user ID.
 * @param string $meta_key   The meta key.
 * @param mixed  $meta_value The meta value.
 * @return void
 */
function lde_fix_access_date_on_meta_change( $meta_id, $user_id, $meta_key, $meta_value ) {
	if ( preg_match( '/^course_(\d+)_access_from$/', $meta_key, $matches ) ) {
		if ( empty( $meta_value ) || intval( $meta_value ) <= 86400 ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$registration_timestamp = strtotime( $user->user_registered );
				update_user_meta( $user_id, $meta_key, $registration_timestamp );
			}
		}
	}
}
add_action( 'updated_user_meta', 'lde_fix_access_date_on_meta_change', 10, 4 );
add_action( 'added_user_meta', 'lde_fix_access_date_on_meta_change', 10, 4 );

/**
 * Corrects course access date when course access is granted.
 *
 * @since 1.5.0
 *
 * @param int $user_id   The user ID.
 * @param int $course_id The course ID.
 * @return void
 */
function lde_fix_access_date_on_assignment( $user_id, $course_id ) {
	$meta_key = 'course_' . $course_id . '_access_from';
	$current  = get_user_meta( $user_id, $meta_key, true );

	if ( empty( $current ) || intval( $current ) <= 86400 ) {
		$user = get_userdata( $user_id );
		if ( $user ) {
			$registration_timestamp = strtotime( $user->user_registered );
			update_user_meta( $user_id, $meta_key, $registration_timestamp );
		}
	}
}
add_action( 'ld_added_course_access', 'lde_fix_access_date_on_assignment', 10, 2 );
add_action( 'learndash_user_course_access_changed', 'lde_fix_access_date_on_assignment', 10, 2 );

/**
 * Corrects course access date when a course activity is updated.
 *
 * @since 1.5.0
 *
 * @param array $args Activity update arguments.
 * @return void
 */
function lde_fix_access_date_on_activity( $args ) {
	if ( ! isset( $args['user_id'], $args['course_id'] ) ) {
		return;
	}

	if ( isset( $args['activity_type'] ) && 'course' === $args['activity_type'] ) {
		lde_fix_access_date_on_assignment( $args['user_id'], $args['course_id'] );
	}
}
add_action( 'learndash_update_user_activity', 'lde_fix_access_date_on_activity', 10, 1 );
