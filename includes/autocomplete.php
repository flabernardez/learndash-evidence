<?php
/**
 * Auto-complete and checkbox gating for LearnDash Evidence.
 *
 * Automatically marks lessons and topics as complete when viewed,
 * hides the native "Mark Complete" button, and gates the "Next"
 * navigation button behind checkbox acknowledgement.
 *
 * @package learndash-evidence
 * @since   1.6.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hides the native LearnDash "Mark Complete" button for lessons and topics.
 *
 * @since 1.3.0
 *
 * @param string $html The button HTML.
 * @param object $post The current post object.
 * @return string Empty string if logged in on lesson/topic, original HTML otherwise.
 */
function lde_remove_mark_complete_button( $html, $post ) {
	if ( is_user_logged_in() && in_array( get_post_type( $post ), array( 'sfwd-lessons', 'sfwd-topic' ), true ) ) {
		return '';
	}
	return $html;
}
add_filter( 'learndash_mark_complete', 'lde_remove_mark_complete_button', 10, 2 );

/**
 * Auto-completes a lesson or topic when a logged-in user views it.
 *
 * @since 1.3.0
 *
 * @return void
 */
function lde_autocomplete_on_view() {
	if ( ! is_user_logged_in() ) {
		return;
	}

	global $post;
	if ( ! $post ) {
		return;
	}

	$user_id   = get_current_user_id();
	$post_type = get_post_type( $post );

	if ( in_array( $post_type, array( 'sfwd-lessons', 'sfwd-topic' ), true ) ) {
		if ( ! learndash_is_lesson_complete( $user_id, $post->ID ) ) {
			learndash_process_mark_complete( $user_id, $post->ID );
		}
	}
}
add_action( 'template_redirect', 'lde_autocomplete_on_view' );

/**
 * Enqueues the checkbox gating script on lesson and topic pages.
 *
 * @since 1.3.0
 *
 * @return void
 */
function lde_enqueue_checkboxes_script() {
	if ( ! is_singular( array( 'sfwd-lessons', 'sfwd-topic' ) ) ) {
		return;
	}

	wp_enqueue_script( 'jquery' );
	wp_enqueue_script(
		'learndash-evidence-checkboxes',
		'',
		array( 'jquery' ),
		null,
		true
	);
	add_action( 'wp_print_footer_scripts', 'lde_inline_checkbox_script', 100 );
}
add_action( 'wp_enqueue_scripts', 'lde_enqueue_checkboxes_script' );

/**
 * Outputs inline JS that disables the "Next" button until all checkboxes are checked.
 *
 * @since 1.3.0
 *
 * @return void
 */
function lde_inline_checkbox_script() {
	?>
	<script>
		jQuery(function($){
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

			function getNextButton() {
				var btns = $('.ld-content-actions .ld-button');
				var nextBtn = btns.filter(function(){
					var txt = $(this).find('.ld-text').text().trim().toLowerCase();
					return txt.includes('<?php echo esc_js( strtolower( esc_html__( 'next topic', 'learndash-evidence' ) ) ); ?>')
						|| txt.includes('<?php echo esc_js( strtolower( esc_html__( 'next lesson', 'learndash-evidence' ) ) ); ?>')
						|| txt.includes('<?php echo esc_js( strtolower( esc_html__( 'next quiz', 'learndash-evidence' ) ) ); ?>')
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

			toggleNextButton();
			$(document).on('change', 'input[type="checkbox"]', function(){
				toggleNextButton();
			});
		});
	</script>
	<?php
}
