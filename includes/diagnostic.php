<?php
/**
 * LearnDash 5.0 compatibility diagnostic for LearnDash Evidence.
 *
 * Runs a series of checks to verify that all LearnDash functions,
 * hooks, and data structures used by this plugin still work after
 * upgrading LearnDash.
 *
 * @package learndash-evidence
 * @since   1.7.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the diagnostic page with all test results.
 *
 * @since 1.7.0
 *
 * @return void
 */
function lde_diagnostic_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Unauthorized access.', 'learndash-evidence' ) );
	}

	$results = lde_run_diagnostic();

	$total  = count( $results );
	$passed = count( array_filter( $results, function ( $r ) {
		return 'pass' === $r['status'];
	} ) );
	$failed = $total - $passed;

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__( 'LearnDash Evidence — Diagnostic', 'learndash-evidence' ) . '</h1>';
	echo '<p>' . esc_html__( 'This diagnostic verifies that all LearnDash functions, hooks, and data structures used by this plugin are available and working correctly.', 'learndash-evidence' ) . '</p>';

	// LearnDash version info.
	$ld_version = defined( 'LEARNDASH_VERSION' ) ? LEARNDASH_VERSION : __( 'Not detected', 'learndash-evidence' );
	echo '<p><strong>LearnDash version:</strong> ' . esc_html( $ld_version ) . ' &nbsp;|&nbsp; ';
	echo '<strong>LearnDash Evidence version:</strong> ' . esc_html( LDE_VERSION ) . '</p>';

	// Summary.
	$summary_class = ( 0 === $failed ) ? 'notice-success' : 'notice-error';
	echo '<div class="notice ' . esc_attr( $summary_class ) . '" style="padding:12px;font-size:14px;">';
	if ( 0 === $failed ) {
		echo '<strong>' . esc_html__( 'ALL TESTS PASSED', 'learndash-evidence' ) . '</strong> — ';
		printf( esc_html__( '%d / %d checks OK.', 'learndash-evidence' ), $passed, $total );
	} else {
		echo '<strong>' . esc_html__( 'SOME TESTS FAILED', 'learndash-evidence' ) . '</strong> — ';
		printf( esc_html__( '%1$d passed, %2$d failed out of %3$d checks.', 'learndash-evidence' ), $passed, $failed, $total );
	}
	echo '</div>';

	// Detail table.
	echo '<table class="wp-list-table widefat fixed striped" style="margin-top:16px;">';
	echo '<thead><tr>';
	echo '<th style="width:40px;">' . esc_html__( '#', 'learndash-evidence' ) . '</th>';
	echo '<th style="width:80px;">' . esc_html__( 'Result', 'learndash-evidence' ) . '</th>';
	echo '<th style="width:200px;">' . esc_html__( 'Category', 'learndash-evidence' ) . '</th>';
	echo '<th>' . esc_html__( 'Test', 'learndash-evidence' ) . '</th>';
	echo '<th>' . esc_html__( 'Details', 'learndash-evidence' ) . '</th>';
	echo '</tr></thead><tbody>';

	$i = 1;
	foreach ( $results as $result ) {
		$icon  = ( 'pass' === $result['status'] ) ? '&#9989;' : '&#10060;';
		$color = ( 'pass' === $result['status'] ) ? '#4CAF50' : '#c0392b';
		echo '<tr>';
		echo '<td>' . intval( $i ) . '</td>';
		echo '<td style="color:' . esc_attr( $color ) . ';font-weight:bold;">' . $icon . ' ' . esc_html( strtoupper( $result['status'] ) ) . '</td>';
		echo '<td>' . esc_html( $result['category'] ) . '</td>';
		echo '<td>' . esc_html( $result['test'] ) . '</td>';
		echo '<td><code>' . esc_html( $result['detail'] ) . '</code></td>';
		echo '</tr>';
		$i++;
	}

	echo '</tbody></table>';
	echo '</div>';
}

/**
 * Runs all diagnostic checks and returns results.
 *
 * @since 1.7.0
 *
 * @return array List of associative arrays with keys: status, category, test, detail.
 */
function lde_run_diagnostic() {
	$results = array();

	// ---------------------------------------------------------------
	// 1. LearnDash active check.
	// ---------------------------------------------------------------
	$results[] = lde_diag_check(
		'Environment',
		'LearnDash plugin active',
		defined( 'LEARNDASH_VERSION' ),
		defined( 'LEARNDASH_VERSION' ) ? 'Version ' . LEARNDASH_VERSION : 'LEARNDASH_VERSION constant not defined'
	);

	// ---------------------------------------------------------------
	// 2. Required LD functions existence.
	// ---------------------------------------------------------------
	$required_functions = array(
		// report.php.
		'learndash_user_get_course_progress' => 'report.php — progress calculation',
		'learndash_get_course_quiz_list'     => 'report.php — collect course quizzes',
		'learndash_get_lesson_list'          => 'report.php — list lessons',
		'learndash_get_lesson_quiz_list'     => 'report.php — lesson/topic quizzes',
		'learndash_topic_dots'               => 'report.php — list topics under lesson',
		'learndash_is_topic_complete'        => 'report.php — evidence completion check',
		// autocomplete.php.
		'learndash_is_lesson_complete'       => 'autocomplete.php — check before marking',
		'learndash_process_mark_complete'    => 'autocomplete.php — auto-complete on view',
		// admin-menus.php / profile.php / student-list.php.
		'learndash_user_get_enrolled_courses' => 'admin-menus.php, profile.php, student-list.php',
	);

	foreach ( $required_functions as $func_name => $usage ) {
		$exists    = function_exists( $func_name );
		$results[] = lde_diag_check(
			'Functions',
			$func_name . '()',
			$exists,
			$exists ? 'Available — used in ' . $usage : 'MISSING — needed by ' . $usage
		);
	}

	// ---------------------------------------------------------------
	// 3. Required LD hooks/filters existence check.
	//    We check that LD has registered at least one callback.
	// ---------------------------------------------------------------
	$required_hooks = array(
		'learndash_mark_complete'                  => array( 'type' => 'filter', 'file' => 'autocomplete.php' ),
		'ld_added_course_access'                   => array( 'type' => 'action', 'file' => 'date-fix.php' ),
		'learndash_user_course_access_changed'     => array( 'type' => 'action', 'file' => 'date-fix.php' ),
		'learndash_update_user_activity'           => array( 'type' => 'action', 'file' => 'date-fix.php' ),
	);

	foreach ( $required_hooks as $hook_name => $info ) {
		// Our own plugin registers callbacks on these hooks. If has_filter/has_action
		// returns a priority, at least our callback is hooked — meaning the hook name is valid.
		$has = has_filter( $hook_name );
		$results[] = lde_diag_check(
			'Hooks',
			$hook_name . ' (' . $info['type'] . ')',
			false !== $has,
			false !== $has
				? 'Registered (priority ' . $has . ') — used in ' . $info['file']
				: 'No callbacks registered — expected by ' . $info['file']
		);
	}

	// ---------------------------------------------------------------
	// 4. learndash_process_mark_complete() signature check.
	//    Must accept at least ($user_id, $post_id).
	// ---------------------------------------------------------------
	if ( function_exists( 'learndash_process_mark_complete' ) ) {
		$ref    = new ReflectionFunction( 'learndash_process_mark_complete' );
		$params = $ref->getParameters();
		$names  = array_map( function ( $p ) {
			return '$' . $p->getName();
		}, $params );

		$has_user_id = false;
		$has_post_id = false;
		foreach ( $params as $param ) {
			$name = $param->getName();
			if ( in_array( $name, array( 'user_id', 'userid', 'user' ), true ) ) {
				$has_user_id = true;
			}
			if ( in_array( $name, array( 'post_id', 'postid', 'post', 'id' ), true ) ) {
				$has_post_id = true;
			}
		}

		// At minimum two params.
		$sig_ok    = count( $params ) >= 2;
		$results[] = lde_diag_check(
			'Signatures',
			'learndash_process_mark_complete() params',
			$sig_ok,
			'Signature: (' . implode( ', ', $names ) . ') — ' . ( $sig_ok ? 'OK' : 'Expected at least 2 params' )
		);
	}

	// ---------------------------------------------------------------
	// 5. LD post types registered.
	// ---------------------------------------------------------------
	$required_post_types = array( 'sfwd-courses', 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz' );
	foreach ( $required_post_types as $pt ) {
		$registered = post_type_exists( $pt );
		$results[]  = lde_diag_check(
			'Post Types',
			$pt,
			$registered,
			$registered ? 'Registered' : 'NOT registered — LearnDash may not be active'
		);
	}

	// ---------------------------------------------------------------
	// 6. LD taxonomy registered.
	// ---------------------------------------------------------------
	$tax_exists = taxonomy_exists( 'ld_topic_tag' );
	$results[]  = lde_diag_check(
		'Taxonomies',
		'ld_topic_tag',
		$tax_exists,
		$tax_exists ? 'Registered — used for "evidencia" tag filtering' : 'NOT registered'
	);

	// ---------------------------------------------------------------
	// 7. Data structure tests with real data.
	//    Uses user_id=207 (FADILA HARCHOUM) and course_id=969 as test case.
	// ---------------------------------------------------------------
	$test_user_id   = 207;
	$test_course_id = 969;
	$test_user      = get_userdata( $test_user_id );

	if ( $test_user && function_exists( 'learndash_user_get_enrolled_courses' ) ) {

		// 7a. Enrolled courses returns array.
		$enrolled = learndash_user_get_enrolled_courses( $test_user_id );
		$results[] = lde_diag_check(
			'Data — Enrollment',
			'learndash_user_get_enrolled_courses() returns array',
			is_array( $enrolled ),
			is_array( $enrolled ) ? count( $enrolled ) . ' course(s) enrolled' : 'Returned: ' . gettype( $enrolled )
		);

		// 7b. Course exists in enrollment.
		$course_enrolled = is_array( $enrolled ) && in_array( $test_course_id, $enrolled, false );
		$results[] = lde_diag_check(
			'Data — Enrollment',
			'Test user enrolled in course ' . $test_course_id,
			$course_enrolled,
			$course_enrolled ? 'Yes' : 'User ' . $test_user_id . ' is not enrolled in course ' . $test_course_id
		);

		// 7c. learndash_user_get_course_progress() structure.
		if ( function_exists( 'learndash_user_get_course_progress' ) ) {
			$ld_progress = learndash_user_get_course_progress( $test_user_id, $test_course_id );
			$has_keys    = is_array( $ld_progress ) && array_key_exists( 'completed', $ld_progress ) && array_key_exists( 'total', $ld_progress );
			$results[]   = lde_diag_check(
				'Data — Progress',
				'Course progress has "completed" and "total" keys',
				$has_keys,
				$has_keys
					? 'completed=' . $ld_progress['completed'] . ', total=' . $ld_progress['total']
					: 'Unexpected structure: ' . wp_json_encode( $ld_progress )
			);
		}

		// 7d. learndash_get_course_quiz_list() structure.
		if ( function_exists( 'learndash_get_course_quiz_list' ) ) {
			$course_quizzes = learndash_get_course_quiz_list( $test_course_id );
			$is_array       = is_array( $course_quizzes );
			$results[]      = lde_diag_check(
				'Data — Quizzes',
				'learndash_get_course_quiz_list() returns array',
				$is_array,
				$is_array ? count( $course_quizzes ) . ' course-level quiz(zes)' : 'Returned: ' . gettype( $course_quizzes )
			);

			// Check item structure: each should have ['post']->ID.
			if ( $is_array && ! empty( $course_quizzes ) ) {
				$first       = reset( $course_quizzes );
				$has_post_id = ( is_array( $first ) && isset( $first['post']->ID ) )
					|| ( is_object( $first ) && isset( $first->ID ) );
				$results[] = lde_diag_check(
					'Data — Quizzes',
					'Quiz list item has [post]->ID or ->ID',
					$has_post_id,
					$has_post_id ? 'Structure OK' : 'Unexpected item structure: ' . wp_json_encode( $first )
				);
			}
		}

		// 7e. learndash_get_lesson_list() structure.
		if ( function_exists( 'learndash_get_lesson_list' ) ) {
			$lessons  = learndash_get_lesson_list( $test_course_id );
			$is_array = is_array( $lessons );
			$results[] = lde_diag_check(
				'Data — Lessons',
				'learndash_get_lesson_list() returns array',
				$is_array,
				$is_array ? count( $lessons ) . ' lesson(s)' : 'Returned: ' . gettype( $lessons )
			);

			// Check item is WP_Post.
			if ( $is_array && ! empty( $lessons ) ) {
				$first    = reset( $lessons );
				$is_post  = $first instanceof WP_Post;
				$results[] = lde_diag_check(
					'Data — Lessons',
					'Lesson list items are WP_Post objects',
					$is_post,
					$is_post ? 'WP_Post #' . $first->ID : 'Unexpected type: ' . gettype( $first )
				);
			}
		}

		// 7f. learndash_topic_dots() structure.
		if ( function_exists( 'learndash_topic_dots' ) && ! empty( $lessons ) ) {
			$first_lesson = reset( $lessons );
			$topics       = learndash_topic_dots( $first_lesson->ID, false, 'array', null, $test_course_id );
			$is_array     = is_array( $topics );
			$results[]    = lde_diag_check(
				'Data — Topics',
				'learndash_topic_dots() returns array',
				$is_array,
				$is_array ? count( $topics ) . ' topic(s) under lesson #' . $first_lesson->ID : 'Returned: ' . gettype( $topics )
			);
		}

		// 7g. User quiz meta structure.
		$user_quizzes = get_user_meta( $test_user_id, '_sfwd-quizzes', true );
		$has_quizzes  = is_array( $user_quizzes ) && ! empty( $user_quizzes );
		$results[]    = lde_diag_check(
			'Data — Quiz Meta',
			'_sfwd-quizzes user meta is non-empty array',
			$has_quizzes,
			$has_quizzes ? count( $user_quizzes ) . ' attempt(s)' : 'Empty or not found'
		);

		if ( $has_quizzes ) {
			$first_attempt  = reset( $user_quizzes );
			$expected_keys  = array( 'quiz', 'course', 'pass', 'percentage' );
			$missing_keys   = array();
			foreach ( $expected_keys as $key ) {
				if ( ! array_key_exists( $key, $first_attempt ) ) {
					$missing_keys[] = $key;
				}
			}
			$keys_ok   = empty( $missing_keys );
			$results[] = lde_diag_check(
				'Data — Quiz Meta',
				'Quiz attempt has required keys (quiz, course, pass, percentage)',
				$keys_ok,
				$keys_ok ? 'All keys present' : 'Missing keys: ' . implode( ', ', $missing_keys )
			);
		}

		// 7h. lde_calculate_real_progress() integration test.
		if ( function_exists( 'lde_calculate_real_progress' ) && $has_quizzes ) {
			$progress = lde_calculate_real_progress( $test_user_id, $test_course_id, $user_quizzes );
			$struct_ok = is_array( $progress )
				&& array_key_exists( 'steps_completed', $progress )
				&& array_key_exists( 'steps_total', $progress )
				&& array_key_exists( 'percent', $progress )
				&& array_key_exists( 'quiz_ids_ordered', $progress );
			$results[] = lde_diag_check(
				'Integration',
				'lde_calculate_real_progress() returns correct structure',
				$struct_ok,
				$struct_ok
					? $progress['steps_completed'] . '/' . $progress['steps_total'] . ' (' . $progress['percent'] . '%), ' . count( $progress['quiz_ids_ordered'] ) . ' quizzes ordered'
					: 'Unexpected structure: ' . wp_json_encode( $progress )
			);

			// Sanity: total should be > 0 and percent 0-100.
			if ( $struct_ok ) {
				$sane = $progress['steps_total'] > 0 && $progress['percent'] >= 0 && $progress['percent'] <= 100;
				$results[] = lde_diag_check(
					'Integration',
					'Progress values are sane',
					$sane,
					'total=' . $progress['steps_total'] . ', percent=' . $progress['percent'] . '%'
				);
			}
		}

	} else {
		$results[] = lde_diag_check(
			'Data',
			'Test user (ID ' . $test_user_id . ') exists',
			false,
			'User not found — skipping data structure tests. Adjust $test_user_id in diagnostic.php if needed.'
		);
	}

	// ---------------------------------------------------------------
	// 8. Course access meta key pattern (date-fix.php).
	// ---------------------------------------------------------------
	if ( $test_user ) {
		$meta_key = 'course_' . $test_course_id . '_access_from';
		$access   = get_user_meta( $test_user_id, $meta_key, true );
		$valid    = ! empty( $access ) && intval( $access ) > 86400;
		$results[] = lde_diag_check(
			'Data — Date Fix',
			'Course access meta (' . $meta_key . ') has valid timestamp',
			$valid,
			$valid ? 'Value: ' . $access . ' (' . date_i18n( 'Y-m-d H:i:s', intval( $access ) ) . ')' : 'Value: ' . var_export( $access, true )
		);
	}

	return $results;
}

/**
 * Helper to build a diagnostic result entry.
 *
 * @since 1.7.0
 *
 * @param string $category Test category label.
 * @param string $test     Test description.
 * @param bool   $passed   Whether the test passed.
 * @param string $detail   Additional detail text.
 * @return array
 */
function lde_diag_check( $category, $test, $passed, $detail = '' ) {
	return array(
		'status'   => $passed ? 'pass' : 'fail',
		'category' => $category,
		'test'     => $test,
		'detail'   => $detail,
	);
}
