<div class="report-wrap">
    <button class="button" onclick="window.print();">
        <?php esc_html_e('Print / Save PDF', 'learndash-evidence'); ?>
    </button>

    <h1><?= esc_html($user->display_name); ?></h1>
    <h2><?= esc_html(get_the_title($course_id)); ?></h2>

    <div class="report-columns">
        <div class="report-section">
            <h3><?php esc_html_e('Course Content', 'learndash-evidence'); ?></h3>
            <div class="box">
                <ul>
                    <?php foreach ($lessons as $lesson): ?>
                        <li><?= esc_html(get_the_title($lesson->ID)); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="report-section">
            <h3><?php esc_html_e('Progress', 'learndash-evidence'); ?></h3>
            <div class="box">
                <p><?= esc_html($progress_percent); ?>% <?php esc_html_e('completed', 'learndash-evidence'); ?></p>
            </div>
        </div>
    </div>

    <div class="report-section">
        <h3><?php esc_html_e('Completed Evidences', 'learndash-evidence'); ?></h3>
        <div class="box">
            <ul>
                <?php foreach ( $evidences as $evidence ) : ?>
                    <li><?= esc_html($evidence); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="report-section">
        <h3><?php esc_html_e('Quizzes', 'learndash-evidence'); ?></h3>
        <div class="box">
            <table>
                <thead>
                <tr>
                    <th><?php esc_html_e('Title', 'learndash-evidence'); ?></th>
                    <th><?php esc_html_e('Score', 'learndash-evidence'); ?></th>
                    <th><?php esc_html_e('Attempts', 'learndash-evidence'); ?></th>
                    <th><?php esc_html_e('Status', 'learndash-evidence'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?= $quiz_rows ?: '<tr><td colspan="4">—</td></tr>'; ?>
                </tbody>
            </table>
        </div>
    </div>

    <p>
        <a class="button" href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . intval($user_id))); ?>">
            &larr; <?php esc_html_e('Back to user profile', 'learndash-evidence'); ?>
        </a>
    </p>
</div>
