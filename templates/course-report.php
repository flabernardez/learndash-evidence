<div class="report-wrap">
	<button class="button" onclick="window.print();">
		<?php esc_html_e( 'Print / Save PDF', 'learndash-evidence' ); ?>
	</button>

	<h1><?php echo esc_html( $user->display_name ); ?></h1>
	<h2><?php echo esc_html( get_the_title( $course_id ) ); ?></h2>

	<div class="report-columns">
		<div class="report-section">
			<h3><?php esc_html_e( 'Course Content', 'learndash-evidence' ); ?></h3>
			<div class="box">
				<ul>
					<?php foreach ( $lessons as $lesson ) : ?>
						<li><?php echo esc_html( get_the_title( $lesson->ID ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>

		<div class="report-section">
			<h3><?php esc_html_e( 'Progress', 'learndash-evidence' ); ?></h3>
			<div class="box">
				<p>
					<strong><?php echo esc_html( $progress_percent ); ?>%</strong>
					<?php esc_html_e( 'completed', 'learndash-evidence' ); ?>
					<br>
					<small style="color:#666;">
						<?php
						printf(
							/* translators: 1: completed steps, 2: total steps */
							esc_html__( '%1$d of %2$d steps (lessons, topics & quizzes)', 'learndash-evidence' ),
							$real_progress['steps_completed'],
							$real_progress['steps_total']
						);
						?>
					</small>
				</p>
			</div>
		</div>
	</div>

	<div class="report-section">
		<h3><?php esc_html_e( 'Completed Evidences', 'learndash-evidence' ); ?></h3>
		<div class="box">
			<ul>
				<?php foreach ( $evidences as $evidence ) : ?>
					<li><?php echo esc_html( $evidence ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>

	<div class="report-section">
		<h3><?php esc_html_e( 'Quizzes', 'learndash-evidence' ); ?></h3>
		<div class="box">
			<table>
				<thead>
				<tr>
					<th><?php esc_html_e( 'Title', 'learndash-evidence' ); ?></th>
					<th><?php esc_html_e( 'Score', 'learndash-evidence' ); ?></th>
					<th><?php esc_html_e( 'Attempts', 'learndash-evidence' ); ?></th>
					<th><?php esc_html_e( 'Status', 'learndash-evidence' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<?php echo $quiz_rows ? $quiz_rows : '<tr><td colspan="4">&mdash;</td></tr>'; ?>
				</tbody>
			</table>
		</div>
	</div>

	<p>
		<a class="button" href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . intval( $user_id ) ) ); ?>">
			&larr; <?php esc_html_e( 'Back to user profile', 'learndash-evidence' ); ?>
		</a>
	</p>
</div>
