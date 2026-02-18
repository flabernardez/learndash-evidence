<?php
/**
 * Evidence report rendering for LearnDash Evidence.
 *
 * Handles the report initialization, data collection, progress calculation
 * (including quizzes in the total), and template rendering.
 *
 * @package learndash-evidence
 * @since   1.6.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_init', 'lde_init_report' );

/**
 * Initializes the evidence report when requested via admin URL.
 *
 * @since 1.3.0
 *
 * @return void
 */
function lde_init_report() {
	if ( is_admin() && isset( $_GET['page'], $_GET['ld_course_report'] ) && 'learndash-evidence' === $_GET['page'] ) {
		add_action( 'admin_head', 'lde_report_styles' );
		add_action( 'admin_footer', 'lde_autoprint_script' );
		add_action( 'admin_notices', 'lde_render_report' );
	}
}

/**
 * Safely retrieves a value from an array or object.
 *
 * @since 1.4.0
 *
 * @param array|object $source  The data source.
 * @param string       $key     The key to look up.
 * @param mixed        $default Default value if key is not found.
 * @return mixed The found value or default.
 */
function lde_get_value( $source, $key, $default = null ) {
	if ( is_array( $source ) && isset( $source[ $key ] ) ) {
		return $source[ $key ];
	}
	if ( is_object( $source ) && isset( $source->$key ) ) {
		return $source->$key;
	}
	return $default;
}

/**
 * Calculates the real course progress including quizzes.
 *
 * LearnDash's native learndash_user_get_course_progress() only counts
 * lessons and topics. This function adds course quizzes to the total
 * so that 100% truly means everything is done.
 *
 * @since 1.6.0
 *
 * @param int   $user_id       The user ID.
 * @param int   $course_id     The course ID.
 * @param array $user_quizzes  The raw quiz data from user meta.
 * @return array {
 *     @type int $steps_completed  Number of completed steps (lessons + topics + passed quizzes).
 *     @type int $steps_total      Total number of steps.
 *     @type int $percent          Progress percentage (0-100).
 * }
 */
function lde_calculate_real_progress( $user_id, $course_id, $user_quizzes ) {
	// Step 1: Get lesson/topic progress from LearnDash.
	$ld_progress    = learndash_user_get_course_progress( $user_id, $course_id );
	$steps_completed = 0;
	$steps_total     = 0;

	if ( is_array( $ld_progress ) ) {
		$steps_completed = isset( $ld_progress['completed'] ) ? intval( $ld_progress['completed'] ) : 0;
		$steps_total     = isset( $ld_progress['total'] ) ? intval( $ld_progress['total'] ) : 0;
	}

	// Step 2: Get all quizzes assigned to this course.
	$course_quizzes = learndash_get_course_quiz_list( $course_id );

	// Also collect quizzes from lessons and topics.
	$lessons = learndash_get_lesson_list( $course_id );
	foreach ( $lessons as $lesson ) {
		$lesson_quizzes = learndash_get_lesson_quiz_list( $lesson->ID, null, $course_id );
		if ( ! empty( $lesson_quizzes ) ) {
			$course_quizzes = array_merge( $course_quizzes, $lesson_quizzes );
		}

		$topics = learndash_topic_dots( $lesson->ID, false, 'array', null, $course_id );
		if ( ! empty( $topics ) && is_array( $topics ) ) {
			foreach ( $topics as $topic ) {
				$topic_id       = is_object( $topic ) ? $topic->ID : ( isset( $topic['post']->ID ) ? $topic['post']->ID : 0 );
				$topic_quizzes  = learndash_get_lesson_quiz_list( $topic_id, null, $course_id );
				if ( ! empty( $topic_quizzes ) ) {
					$course_quizzes = array_merge( $course_quizzes, $topic_quizzes );
				}
			}
		}
	}

	if ( empty( $course_quizzes ) ) {
		$percent = ( $steps_total > 0 ) ? round( ( $steps_completed / $steps_total ) * 100 ) : 0;
		return array(
			'steps_completed' => $steps_completed,
			'steps_total'     => $steps_total,
			'percent'         => $percent,
		);
	}

	// Step 3: Deduplicate quiz IDs.
	$quiz_ids = array();
	foreach ( $course_quizzes as $quiz_item ) {
		$qid = 0;
		if ( is_array( $quiz_item ) && isset( $quiz_item['post']->ID ) ) {
			$qid = $quiz_item['post']->ID;
		} elseif ( is_object( $quiz_item ) && isset( $quiz_item->ID ) ) {
			$qid = $quiz_item->ID;
		}
		if ( $qid > 0 ) {
			$quiz_ids[ $qid ] = $qid;
		}
	}

	$total_quizzes  = count( $quiz_ids );
	$passed_quizzes = 0;

	// Step 4: Check which quizzes the user has passed.
	if ( $user_quizzes && is_array( $user_quizzes ) ) {
		foreach ( $quiz_ids as $quiz_id ) {
			foreach ( $user_quizzes as $attempt ) {
				if ( intval( $attempt['quiz'] ) === $quiz_id && intval( $attempt['course'] ) === $course_id ) {
					if ( ! empty( $attempt['pass'] ) ) {
						$passed_quizzes++;
						break; // One pass is enough.
					}
				}
			}
		}
	}

	// Step 5: Combine.
	$steps_total     += $total_quizzes;
	$steps_completed += $passed_quizzes;
	$percent          = ( $steps_total > 0 ) ? round( ( $steps_completed / $steps_total ) * 100 ) : 0;

	return array(
		'steps_completed' => $steps_completed,
		'steps_total'     => $steps_total,
		'percent'         => $percent,
	);
}

/**
 * Renders the full evidence report for a user/course combination.
 *
 * Collects lessons, progress (with quizzes), evidence items, and quiz
 * results, then includes the report template.
 *
 * @since 1.3.0
 *
 * @return void
 */
function lde_render_report() {
	$course_id = intval( $_GET['ld_course_report'] );
	$user_id   = intval( $_GET['user_id'] );
	$user      = get_userdata( $user_id );

	if ( ! $user || ! $course_id ) {
		return;
	}

	$lessons = learndash_get_lesson_list( $course_id );

	// Get raw quiz data early so we can use it for progress calculation.
	$user_quizzes = get_user_meta( $user_id, '_sfwd-quizzes', true );

	// Calculate real progress including quizzes.
	$real_progress    = lde_calculate_real_progress( $user_id, $course_id, $user_quizzes );
	$progress_percent = $real_progress['percent'];

	// Get all topics in course with "evidencia" tag.
	$evidence_topics = get_posts( array(
		'post_type'      => 'sfwd-topic',
		'tax_query'      => array(
			array(
				'taxonomy' => 'ld_topic_tag',
				'field'    => 'slug',
				'terms'    => 'evidencia',
			),
		),
		'meta_query'     => array(
			array(
				'key'     => 'course_id',
				'value'   => $course_id,
				'compare' => '=',
			),
		),
		'posts_per_page' => -1,
	) );

	// Prepare evidence lines from excerpt.
	$evidences = array();
	foreach ( $evidence_topics as $topic ) {
		$excerpt = wp_strip_all_tags( get_the_excerpt( $topic ) );
		$lines   = preg_split( '/\r\n|\r|\n/', $excerpt );

		$is_completed = learndash_is_topic_complete( $user_id, $topic->ID );

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( $line && ! in_array( $topic->ID . $line, $evidences, true ) ) {
				$icon        = $is_completed ? "\xE2\x9C\x85" : "\xE2\x9D\x8C";
				$evidences[] = $icon . ' ' . $line;
			}
		}
	}

	// Build quiz results table.
	$quiz_rows       = '';
	$quizzes_grouped = array();

	if ( $user_quizzes && is_array( $user_quizzes ) ) {
		usort( $user_quizzes, function ( $a, $b ) {
			$time_a = isset( $a['time'] ) ? $a['time'] : 0;
			$time_b = isset( $b['time'] ) ? $b['time'] : 0;
			return $time_b <=> $time_a;
		} );

		foreach ( $user_quizzes as $quiz ) {
			if ( intval( $quiz['course'] ) !== $course_id ) {
				continue;
			}
			$quiz_id = $quiz['quiz'];
			if ( ! isset( $quizzes_grouped[ $quiz_id ] ) ) {
				$quizzes_grouped[ $quiz_id ] = $quiz;
			}
		}
	}

	foreach ( $quizzes_grouped as $quiz_id => $quiz ) {
		$quiz_post = get_post( $quiz_id );
		$title     = $quiz_post ? $quiz_post->post_title : "\xE2\x80\x94";

		$percentage = 0;
		if ( isset( $quiz['percentage'] ) ) {
			$percentage = floatval( $quiz['percentage'] );
		} elseif ( isset( $quiz['score_percentage'] ) ) {
			$percentage = floatval( $quiz['score_percentage'] );
		} elseif ( isset( $quiz['count'] ) && $quiz['count'] > 0 ) {
			$percentage = ( floatval( $quiz['score'] ) / floatval( $quiz['count'] ) ) * 100;
		}

		$pass_status = isset( $quiz['pass'] ) ? (bool) $quiz['pass'] : false;

		if ( $percentage > 0 ) {
			$status_text  = $pass_status ? esc_html__( 'Passed', 'learndash-evidence' ) : esc_html__( 'Not passed', 'learndash-evidence' );
			$status_style = $pass_status ? 'background:#4CAF50;color:#fff;' : 'background:#c0392b;color:#fff;';
		} else {
			$status_text  = esc_html__( 'Not taken', 'learndash-evidence' );
			$status_style = 'background:#ccc;color:#333;';
		}

		$attempt_count = 0;
		foreach ( $user_quizzes as $q ) {
			if ( intval( $q['quiz'] ) === intval( $quiz_id ) && intval( $q['course'] ) === $course_id ) {
				$attempt_count++;
			}
		}

		$quiz_rows .= sprintf(
			'<tr><td>%s</td><td>%s%%</td><td>%s</td><td><span style="padding:2px 6px;border-radius:4px;font-weight:bold;%s">%s</span></td></tr>',
			esc_html( $title ),
			round( $percentage, 2 ),
			$attempt_count,
			esc_attr( $status_style ),
			esc_html( $status_text )
		);
	}

	include plugin_dir_path( dirname( __FILE__ ) ) . 'templates/course-report.php';
	exit;
}
