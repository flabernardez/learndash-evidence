<?php
/**
 * Plugin Name: LearnDash Evidence
 * Description: Adds a tracking section in LearnDash to monitor user progress and quiz scores, prepared for screencapture or print.
 * Version: 1.4
 * Author: Flavia Bernárdez Rodríguez
 * Author URI: https://flabernardez.com
 * License: GPL v3 or later
 * Text Domain: learndash-evidence
 */

defined('ABSPATH') || exit;

// Register admin submenus (hidden report and visible student tracking under LearnDash)
add_action( 'admin_menu', 'learndash_evidence_add_submenus' );
function learndash_evidence_add_submenus() {
    // Hidden subpage for evidence report
    add_submenu_page(
        null,
        esc_html__('LearnDash Evidence', 'learndash-evidence'),
        esc_html__('LearnDash Evidence', 'learndash-evidence'),
        'edit_users',
        'learndash-evidence',
        'learndash_evidence_user_courses_list'
    );

    // Visible subpage for student tracking under LearnDash
    add_submenu_page(
        'learndash-lms',
        esc_html__('Student Tracking', 'learndash-evidence'),
        esc_html__('Student Tracking', 'learndash-evidence'),
        'edit_users',
        'student-list',
        function() {
            include plugin_dir_path(__FILE__) . 'student-list.php';
        }
    );
}

// Enable excerpt support for LearnDash Topics
add_action( 'init', function() {
    add_post_type_support( 'sfwd-topic', 'excerpt' );
});

// Display list of user courses (used for hidden report page)
function learndash_evidence_user_courses_list() {
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : get_current_user_id();
    $user = get_userdata($user_id);
    $courses = learndash_user_get_enrolled_courses($user_id);

    echo '<div class="wrap"><h1>' . esc_html($user->display_name) . '</h1><ul>';
    foreach ( $courses as $course_id ) {
        $title = get_the_title($course_id);
        if ( ! $title ) continue;
        $url = admin_url('users.php?page=learndash-evidence&ld_course_report=' . $course_id . '&user_id=' . $user_id);
        echo '<li><a href="' . esc_url($url) . '">' . esc_html($title) . '</a></li>';
    }
    echo '</ul></div>';
}

// Render evidence report on the hidden page
add_action( 'admin_init', 'learndash_evidence_init_report' );
function learndash_evidence_init_report() {
    if ( is_admin() && isset($_GET['page'], $_GET['ld_course_report']) && $_GET['page'] === 'learndash-evidence' ) {
        add_action( 'admin_head', 'learndash_evidence_styles' );
        add_action( 'admin_footer', 'learndash_evidence_autoprint' );
        add_action( 'admin_notices', 'learndash_evidence_render_report' );
    }
}

// Print and report styles
function learndash_evidence_styles() {
    echo '<style>
        @media print {
            @page { size: A4; margin: 0; }
            html, body { margin:0; padding:0; background:#fff; }
            body * { visibility: hidden !important; }
            .report-wrap, .report-wrap * { visibility: visible !important; }
            .report-wrap {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                width: 210mm !important;
                min-height: 297mm !important;
                max-width: none !important;
                margin: 0 !important;
                padding: 1.5cm 1.5cm 1cm 1.5cm !important;
                background: #fff !important;
                box-sizing: border-box !important;
                z-index: 99999 !important;
            }
            .button { display: none !important; }
        }
        body { background:#f5f5f5;font-family:sans-serif; }
        .report-wrap { background:#fff;padding:2em;max-width:960px;margin:2em auto;box-shadow:0 0 10px rgba(0,0,0,.1); }
        .report-section { margin-bottom:2em; }
        .box { border:1px solid #ddd;padding:1em;margin-bottom:1em;background:#fdfdfd; }
        table { width:100%;border-collapse:collapse;font-size:14px; }
        th,td { border:1px solid #ddd;padding:.5em;text-align:left; }
        .report-columns { display: flex; gap: 2em; align-items: flex-start; margin-bottom: 2em; }
        .report-columns .report-section { flex: 1 1 0; min-width: 0; }
        @media (max-width: 700px) {
            .report-columns { flex-direction: column; gap: 1em; }
        }
        @media print {
            .report-columns {
                display: flex !important;
                gap: 1.5cm !important;
                align-items: flex-start !important;
            }
            .report-columns .report-section {
                flex: 1 1 0 !important;
                min-width: 0 !important;
                margin-bottom: 0 !important;
            }
        }
    </style>';
}

// Print automatically if ?print
function learndash_evidence_autoprint() {
    echo '<script>window.onload=function(){if(location.hash==="#print")window.print();}</script>';
}

// Safe get helper
function learndash_evidence_get_value($source, $key, $default = null) {
    if (is_array($source) && isset($source[$key])) return $source[$key];
    if (is_object($source) && isset($source->$key)) return $source->$key;
    return $default;
}

// Evidence report logic
function learndash_evidence_render_report() {
    $course_id = intval($_GET['ld_course_report']);
    $user_id   = intval($_GET['user_id']);
    $user      = get_userdata($user_id);

    if (!$user || !$course_id) return;

    $lessons = learndash_get_lesson_list($course_id);
    $progress = learndash_user_get_course_progress($user_id, $course_id);
    $progress_percent = is_array($progress) && $progress['total'] ? round(($progress['completed'] / $progress['total']) * 100) : 0;

    // Get all topics in course with "evidencia" tag
    $evidence_topics = get_posts(array(
        'post_type' => 'sfwd-topic',
        'tax_query' => array(
            array(
                'taxonomy' => 'ld_topic_tag',
                'field'    => 'slug',
                'terms'    => 'evidencia',
            ),
        ),
        'meta_query' => array(
            array(
                'key' => 'course_id',
                'value' => $course_id,
                'compare' => '='
            )
        ),
        'posts_per_page' => -1,
    ));

    // Prepare evidence lines from excerpt
    $evidences = array();
    foreach ( $evidence_topics as $topic ) {
        $excerpt = strip_tags( get_the_excerpt( $topic ) );
        $lines = preg_split('/\r\n|\r|\n/', $excerpt);

        // Check if topic is completed for this user
        $is_completed = learndash_is_topic_complete( $user_id, $topic->ID );

        foreach ( $lines as $line ) {
            $line = trim($line);
            if ( $line && !in_array($topic->ID . $line, $evidences) ) {
                $icon = $is_completed ? '✅' : '❌';
                $evidences[] = $icon . ' ' . $line;
            }
        }
    }

    $user_quizzes = get_user_meta($user_id, '_sfwd-quizzes', true);
    $quiz_rows = '';
    $quizzes_grouped = [];

    if ($user_quizzes && is_array($user_quizzes)) {
        usort($user_quizzes, function($a, $b) {
            $time_a = isset($a['time']) ? $a['time'] : 0;
            $time_b = isset($b['time']) ? $b['time'] : 0;
            return $time_b <=> $time_a;
        });

        foreach ($user_quizzes as $quiz) {
            if ($quiz['course'] != $course_id) continue;
            $quiz_id = $quiz['quiz'];
            if (!isset($quizzes_grouped[$quiz_id])) {
                $quizzes_grouped[$quiz_id] = $quiz;
            }
        }
    }

    foreach ($quizzes_grouped as $quiz_id => $quiz) {
        $quiz_post = get_post($quiz_id);
        $title = $quiz_post ? $quiz_post->post_title : '—';

        $percentage = 0;
        if (isset($quiz['percentage'])) {
            $percentage = floatval($quiz['percentage']);
        } elseif (isset($quiz['score_percentage'])) {
            $percentage = floatval($quiz['score_percentage']);
        } elseif (isset($quiz['count']) && $quiz['count'] > 0) {
            $percentage = (floatval($quiz['score']) / floatval($quiz['count'])) * 100;
        }

        $pass_status = isset($quiz['pass']) ? (bool)$quiz['pass'] : false;
        $passing_percentage = isset($quiz['passpercent']) ? floatval($quiz['passpercent']) : 0;

        if ($percentage > 0) {
            $status_text = $pass_status ? esc_html__('Passed', 'learndash-evidence') : esc_html__('Not passed', 'learndash-evidence');
            $status_style = $pass_status ? 'background:#4CAF50;color:#fff;' : 'background:#c0392b;color:#fff;';
        } else {
            $status_text = esc_html__('Not taken', 'learndash-evidence');
            $status_style = 'background:#ccc;color:#333;';
        }

        $attempt_count = 0;
        foreach ($user_quizzes as $q) {
            if ($q['quiz'] == $quiz_id && $q['course'] == $course_id) {
                $attempt_count++;
            }
        }

        $quiz_rows .= sprintf(
            '<tr><td>%s</td><td>%s%%</td><td>%s</td><td><span style="padding:2px 6px;border-radius:4px;font-weight:bold;%s">%s</span></td></tr>',
            esc_html($title),
            round($percentage, 2),
            $attempt_count,
            esc_attr($status_style),
            esc_html($status_text)
        );
    }

    include plugin_dir_path(__FILE__) . 'templates/course-report.php';
    exit;
}

// Block for quick access to reports in user profile
add_action('all_admin_notices', 'learndash_evidence_profile_block_top');
function learndash_evidence_profile_block_top() {
    global $pagenow;
    if (!current_user_can('edit_users') || !in_array($pagenow, ['user-edit.php', 'profile.php'])) return;

    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : get_current_user_id();
    $courses = learndash_user_get_enrolled_courses($user_id);
    if (!$courses) return;

    echo '<div class="notice"><h2>' . esc_html__('LearnDash Evidence', 'learndash-evidence') . '</h2><ul>';
    foreach ($courses as $course_id) {
        $title = get_the_title($course_id);
        $url = admin_url('users.php?page=learndash-evidence&ld_course_report=' . $course_id . '&user_id=' . $user_id);
        echo '<li><a href="' . esc_url($url) . '">' . esc_html($title) . '</a></li>';
    }
    echo '</ul></div>';
}

// Hide "Mark Complete" button in all lessons/topics
function learndash_evidence_remove_mark_complete_button_all( $html, $post ) {
    if ( is_user_logged_in() && in_array( get_post_type( $post ), array( 'sfwd-lessons', 'sfwd-topic' ) ) ) {
        return '';
    }
    return $html;
}
add_filter( 'learndash_mark_complete', 'learndash_evidence_remove_mark_complete_button_all', 10, 2 );

// Auto-complete lesson or topic when accessed
function learndash_evidence_autocomplete_on_view() {
    if ( ! is_user_logged_in() ) return;

    global $post;
    if ( ! $post ) return;

    $user_id = get_current_user_id();
    $post_type = get_post_type( $post );

    if ( in_array( $post_type, array( 'sfwd-lessons', 'sfwd-topic' ) ) ) {
        if ( ! learndash_is_lesson_complete( $user_id, $post->ID ) ) {
            learndash_process_mark_complete( $user_id, $post->ID );
        }
    }
}
add_action( 'template_redirect', 'learndash_evidence_autocomplete_on_view' );

// Enqueue the script for checkbox gating on LearnDash lessons and topics
function learndash_evidence_enqueue_checkboxes_script() {
    if ( ! is_singular( array( 'sfwd-lessons', 'sfwd-topic' ) ) ) return;

    wp_enqueue_script('jquery');
    wp_enqueue_script(
        'learndash-evidence-checkboxes',
        '', // Inline script, so no src
        array( 'jquery' ),
        null,
        true
    );
    add_action( 'wp_print_footer_scripts', 'learndash_evidence_inline_checkbox_script', 100 );
}
add_action( 'wp_enqueue_scripts', 'learndash_evidence_enqueue_checkboxes_script' );

// Inline JS for enabling/disabling next button based on checkboxes
function learndash_evidence_inline_checkbox_script() {
    ?>
    <script>
        jQuery(function($){
            // Return true only if ALL checkboxes are checked and at least one exists
            function checkCheckboxes() {
                var checkboxes = $('input[type="checkbox"]');
                if (checkboxes.length === 0) {
                    return true;
                }
                var allChecked = true;
                checkboxes.each(function(){
                    if (!$(this).is(':checked')) {
                        allChecked = false;
                        return false;
                    }
                });
                return allChecked;
            }

            // Get the advance button ("next" type, by label)
            function getNextButton() {
                var btns = $('.ld-content-actions .ld-button');
                var nextBtn = btns.filter(function(){
                    var txt = $(this).find('.ld-text').text().trim().toLowerCase();
                    return txt.includes('<?php echo esc_js( strtolower( esc_html__('next topic', 'learndash-evidence') ) ); ?>')
                        || txt.includes('<?php echo esc_js( strtolower( esc_html__('next lesson', 'learndash-evidence') ) ); ?>')
                        || txt.includes('<?php echo esc_js( strtolower( esc_html__('next quiz', 'learndash-evidence') ) ); ?>')
                        || txt.includes('next');
                });
                return nextBtn.length ? nextBtn : $();
            }

            function toggleNextButton() {
                var nextBtn = getNextButton();
                if (!nextBtn.length) return;
                if (checkCheckboxes()) {
                    nextBtn.prop('disabled', false).removeClass('ld-disabled').css({
                        'pointer-events': '',
                        'opacity': ''
                    });
                } else {
                    nextBtn.prop('disabled', true).addClass('ld-disabled').css({
                        'pointer-events': 'none',
                        'opacity': '0.5'
                    });
                }
            }

            // On load and when checkbox changes
            toggleNextButton();
            $(document).on('change', 'input[type="checkbox"]', function(){
                toggleNextButton();
            });
        });
    </script>
    <?php
}

// Set course access start date when user is created (covers registration)
function learndash_evidence_set_access_date_on_user_register($user_id) {
    $courses = learndash_user_get_enrolled_courses($user_id);
    foreach ($courses as $course_id) {
        $meta_key = 'course_' . $course_id . '_access_from';
        $current = get_user_meta($user_id, $meta_key, true);
        if (empty($current) || $current == '0' || $current == 0 || $current == 'false') {
            update_user_meta($user_id, $meta_key, time());
        }
    }
}
add_action('user_register', 'learndash_evidence_set_access_date_on_user_register', 10, 1);

// Set course access date when meta changes (covers manual assignment, imports, etc)
function learndash_evidence_set_access_date_on_meta_update($meta_id, $user_id, $meta_key, $meta_value) {
    if (preg_match('/^course_(\d+)_access_from$/', $meta_key, $matches)) {
        if (empty($meta_value) || $meta_value == '0' || $meta_value == 0 || $meta_value == 'false') {
            update_user_meta($user_id, $meta_key, time());
        }
    }
}
add_action('updated_user_meta', 'learndash_evidence_set_access_date_on_meta_update', 10, 4);
add_action('added_user_meta',   'learndash_evidence_set_access_date_on_meta_update', 10, 4);


