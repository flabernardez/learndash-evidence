<?php
/**
 * Student List for LearnDash Evidence.
 *
 * Lists students enrolled in any course with search, sortable columns,
 * and report link. Uses real progress calculation (lessons + quizzes).
 *
 * @author  Flavia Bernardez Rodriguez
 * @package learndash-evidence
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Retrieves all students enrolled in any course with real progress data.
 *
 * @since 1.3.0
 * @since 1.6.0 Uses lde_calculate_real_progress() to include quizzes.
 *
 * @return array List of student data arrays.
 */
function lde_get_students_in_courses() {
	$user_query = new WP_User_Query( array(
		'role__in' => array( 'subscriber', 'student' ),
		'number'   => -1,
		'orderby'  => 'display_name',
		'order'    => 'ASC',
	) );
	$users = $user_query->get_results();

	$students = array();
	foreach ( $users as $user ) {
		$courses = learndash_user_get_enrolled_courses( $user->ID );
		foreach ( $courses as $course_id ) {
			$data = array(
				'user_id'      => $user->ID,
				'display_name' => $user->display_name,
				'first_name'   => $user->first_name,
				'last_name'    => $user->last_name,
				'course_id'    => $course_id,
				'course_title' => get_the_title( $course_id ),
				'course_link'  => admin_url( 'admin.php?page=learndash-evidence&ld_course_report=' . intval( $course_id ) . '&user_id=' . intval( $user->ID ) ),
			);

			// Course start date.
			$course_meta        = get_user_meta( $user->ID, 'course_' . $course_id . '_access_from', true );
			$data['start_date'] = $course_meta ? date_i18n( get_option( 'date_format' ), intval( $course_meta ) ) : '';

			// Real completion status including quizzes.
			$user_quizzes    = get_user_meta( $user->ID, '_sfwd-quizzes', true );
			$real_progress   = lde_calculate_real_progress( $user->ID, $course_id, $user_quizzes );
			$data['completed'] = ( $real_progress['percent'] >= 100 )
				? esc_html__( 'Completed', 'learndash-evidence' )
				: esc_html__( 'Not completed', 'learndash-evidence' );

			$students[] = $data;
		}
	}
	return $students;
}

// Process search and ordering.
$students = lde_get_students_in_courses();

$search  = isset( $_GET['user_search'] ) ? trim( sanitize_text_field( $_GET['user_search'] ) ) : '';
$order   = isset( $_GET['order'] ) ? sanitize_text_field( $_GET['order'] ) : 'asc';
$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'display_name';

// Filter by search term.
if ( $search ) {
	$students = array_filter( $students, function ( $s ) use ( $search ) {
		return stripos( $s['display_name'], $search ) !== false
			|| stripos( $s['first_name'], $search ) !== false
			|| stripos( $s['last_name'], $search ) !== false;
	} );
}

// Sort array.
usort( $students, function ( $a, $b ) use ( $orderby, $order ) {
	$val_a = isset( $a[ $orderby ] ) ? $a[ $orderby ] : '';
	$val_b = isset( $b[ $orderby ] ) ? $b[ $orderby ] : '';
	if ( $val_a === $val_b ) {
		return 0;
	}
	return ( 'desc' === $order ? -1 : 1 ) * ( ( $val_a < $val_b ) ? -1 : 1 );
} );

/**
 * Generates a sortable column URL for the student list table.
 *
 * @since 1.3.0
 *
 * @param string $col   The column to sort by.
 * @param string $order The current sort order.
 * @return string The URL with updated sort parameters.
 */
function lde_order_url( $col, $order ) {
	$url    = admin_url( 'admin.php?page=student-list' );
	$params = array(
		'orderby' => $col,
		'order'   => ( 'asc' === $order ) ? 'desc' : 'asc',
	);
	if ( isset( $_GET['user_search'] ) && $_GET['user_search'] ) {
		$params['user_search'] = sanitize_text_field( $_GET['user_search'] );
	}
	return add_query_arg( $params, $url );
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Student List in Courses', 'learndash-evidence' ); ?></h1>
	<form method="get" action="">
		<input type="hidden" name="page" value="student-list" />
		<input type="text" name="user_search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search user...', 'learndash-evidence' ); ?>" />
		<button type="submit" class="button"><?php esc_html_e( 'Search', 'learndash-evidence' ); ?></button>
	</form>
	<br>
	<table class="wp-list-table widefat fixed striped">
		<thead>
		<tr>
			<th><a href="<?php echo esc_url( lde_order_url( 'display_name', $order ) ); ?>"><?php esc_html_e( 'Name', 'learndash-evidence' ); ?></a></th>
			<th><a href="<?php echo esc_url( lde_order_url( 'last_name', $order ) ); ?>"><?php esc_html_e( 'Last Name', 'learndash-evidence' ); ?></a></th>
			<th><a href="<?php echo esc_url( lde_order_url( 'start_date', $order ) ); ?>"><?php esc_html_e( 'Start Date', 'learndash-evidence' ); ?></a></th>
			<th><a href="<?php echo esc_url( lde_order_url( 'course_title', $order ) ); ?>"><?php esc_html_e( 'Course', 'learndash-evidence' ); ?></a></th>
			<th><a href="<?php echo esc_url( lde_order_url( 'completed', $order ) ); ?>"><?php esc_html_e( 'Status', 'learndash-evidence' ); ?></a></th>
			<th><?php esc_html_e( 'Report', 'learndash-evidence' ); ?></th>
		</tr>
		</thead>
		<tbody>
		<?php if ( $students ) : ?>
			<?php foreach ( $students as $s ) : ?>
				<tr>
					<td><?php echo esc_html( $s['display_name'] ); ?></td>
					<td><?php echo esc_html( $s['last_name'] ); ?></td>
					<td><?php echo esc_html( $s['start_date'] ); ?></td>
					<td><?php echo esc_html( $s['course_title'] ); ?></td>
					<td><?php echo esc_html( $s['completed'] ); ?></td>
					<td><a href="<?php echo esc_url( $s['course_link'] ); ?>" class="button"><?php esc_html_e( 'View report', 'learndash-evidence' ); ?></a></td>
				</tr>
			<?php endforeach; ?>
		<?php else : ?>
			<tr><td colspan="6"><?php esc_html_e( 'No students found.', 'learndash-evidence' ); ?></td></tr>
		<?php endif; ?>
		</tbody>
	</table>
</div>
