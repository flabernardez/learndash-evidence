<?php
/**
 * Auto-complete and checkbox gating for LearnDash Evidence.
 *
 * Automatically marks lessons and topics as complete when viewed,
 * hides the native "Mark Complete" / "Completed" status, and gates
 * the "Next" navigation button behind checkbox acknowledgement.
 *
 * Compatible with LearnDash 4.x (LD30 Legacy) and 5.x (LD30 Modern / Breezy).
 * In LD 5.0 Focus Mode, both Legacy and Modern navigation coexist on the page.
 *
 * @package learndash-evidence
 * @since   1.6.0
 * @since   1.7.0 Updated for LD 5.0 Modern/Breezy theme compatibility.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hides the native LearnDash "Mark Complete" button for lessons and topics.
 *
 * In LD 4.x this is sufficient. In LD 5.0 Modern theme, returning empty
 * causes a disabled fallback button to render, so we also inject CSS
 * via lde_frontend_css() to hide the entire progress wrapper.
 *
 * @since 1.3.0
 * @since 1.7.0 Added complementary CSS approach for LD 5.0 Modern theme.
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
 * Skips auto-completion if the content contains checkboxes, because
 * in that case the topic should only complete when all checkboxes
 * are checked (handled via AJAX in lde_ajax_mark_complete).
 *
 * @since 1.3.0
 * @since 1.7.0 Now passes $course_id explicitly for LD 5.0 compatibility.
 * @since 1.8.0 Skips auto-complete for posts with checkboxes.
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
		// Skip if already completed.
		if ( learndash_is_lesson_complete( $user_id, $post->ID ) ) {
			return;
		}

		// Skip auto-complete if the content has checkboxes.
		// The topic will be completed via AJAX when all checkboxes are checked.
		if ( lde_post_has_checkboxes( $post->ID ) ) {
			return;
		}

		$course_id = learndash_get_course_id( $post->ID );
		learndash_process_mark_complete( $user_id, $post->ID, false, $course_id );
	}
}
add_action( 'template_redirect', 'lde_autocomplete_on_view' );

/**
 * Checks whether a post's content contains HTML checkboxes.
 *
 * @since 1.8.0
 *
 * @param int $post_id The post ID.
 * @return bool True if the content has at least one checkbox input.
 */
function lde_post_has_checkboxes( $post_id ) {
	$content = get_post_field( 'post_content', $post_id );

	return ( false !== strpos( $content, 'type="checkbox"' ) );
}

/**
 * Injects CSS fixes for lessons and topics.
 *
 * Covers:
 * - Hide Mark Complete button and "Completed" status (Legacy + Modern).
 * - Checkbox alignment inside content areas (Normal + Focus Mode).
 * - Focus Mode avatar overflow fix for Modern/Breezy theme.
 *
 * @since 1.7.0
 *
 * @return void
 */
function lde_frontend_css() {
	if ( ! is_singular( array( 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz' ) ) || ! is_user_logged_in() ) {
		return;
	}
	?>
	<style>
		/* ==========================================================
		   1. Hide Mark Complete / Completed status
		   ========================================================== */

		/* LD 4.x Legacy: hide mark complete form and button. */
		.sfwd-mark-complete,
		#learndash_mark_complete_button {
			display: none !important;
		}

		/*
		 * LD 5.0 Modern/Breezy: hide the entire progress wrapper.
		 * This covers both the "mark-complete" button (disabled fallback)
		 * and the "completed" status ("Tema Marked Complete").
		 */
		.ld-navigation__progress {
			display: none !important;
		}

		/* ==========================================================
		   2. Checkbox + label alignment in content areas
		   ========================================================== */

		/*
		 * Target checkboxes inside all possible content containers:
		 * - .ld-focus-content       (Focus Mode)
		 * - .ld-tab-bar__panel      (Modern tabbed content)
		 * - .ld-layout__content     (Modern layout)
		 * - .learndash-wrapper      (General wrapper)
		 */
		.ld-focus-content input[type="checkbox"],
		.ld-tab-bar__panel input[type="checkbox"],
		.ld-layout__content input[type="checkbox"],
		.learndash-wrapper input[type="checkbox"] {
			margin-right: 8px;
			margin-top: 3px;
			flex-shrink: 0;
		}

		.ld-focus-content label,
		.ld-tab-bar__panel label,
		.ld-layout__content label,
		.learndash-wrapper label {
			display: flex;
			align-items: flex-start;
		}

		/* ==========================================================
		   3. Sidebar: disable links on incomplete lessons and topics
		   ========================================================== */

		/*
		 * Prevents students from navigating to lessons or topics they
		 * haven't reached yet. Only affects Focus Mode sidebar.
		 * The current lesson/topic remains clickable via specificity override.
		 */

		/* Disable lesson links for incomplete lessons. */
		.ld-lesson-item.learndash-incomplete:not(.ld-is-current-lesson)
			> .ld-lesson-item-preview
			> a.ld-lesson-item-preview-heading {
			pointer-events: none;
			opacity: 0.45;
			cursor: default;
		}

		/* Disable topic links for incomplete topics. */
		.ld-table-list-item.learndash-incomplete
			> a.ld-table-list-item-preview:not(.ld-is-current-item) {
			pointer-events: none;
			opacity: 0.45;
			cursor: default;
		}

		/* Disable quiz links for incomplete quizzes. */
		.ld-table-list-item.learndash-incomplete
			> .ld-table-list-item-wrapper
			> a.ld-table-list-item-preview {
			pointer-events: none;
			opacity: 0.45;
			cursor: default;
		}

		/* Keep the currently active topic fully visible and clickable. */
		.ld-table-list-item.learndash-incomplete
			> a.ld-table-list-item-preview.ld-is-current-item {
			pointer-events: auto;
			opacity: 1;
			cursor: pointer;
		}

		/* ==========================================================
		   4. Focus Mode avatar overflow fix
		   ========================================================== */

		/*
		 * In LD 5.0 Modern mode, the CSS rule that constrains avatar images
		 * to width:100% is excluded via :not(.learndash-wrapper--modern).
		 * The avatar img renders at native size (96px) inside a 40px container.
		 */
		.ld-focus-header .ld-profile-avatar img {
			width: 100%;
			height: 100%;
			object-fit: cover;
			border-radius: 50%;
		}
	</style>
	<?php
}
add_action( 'wp_head', 'lde_frontend_css' );

/**
 * Enqueues the checkbox gating script on lesson and topic pages.
 *
 * Also localizes AJAX variables for persistent checkbox state.
 *
 * @since 1.3.0
 * @since 1.8.0 Added AJAX localization for checkbox persistence.
 *
 * @return void
 */
function lde_enqueue_checkboxes_script() {
	if ( ! is_singular( array( 'sfwd-lessons', 'sfwd-topic' ) ) ) {
		return;
	}

	wp_enqueue_script( 'jquery' );
	add_action( 'wp_print_footer_scripts', 'lde_inline_checkbox_script', 100 );
}
add_action( 'wp_enqueue_scripts', 'lde_enqueue_checkboxes_script' );

/**
 * Outputs inline JS that disables ALL "Next" buttons until all checkboxes are checked.
 *
 * In LD 5.0 Focus Mode, both Legacy and Modern navigation coexist on the page:
 * - Legacy:  .ld-content-actions .ld-button  (in the masthead)
 * - Modern:  .ld-navigation__next-link       (in the content area)
 *
 * This script finds and disables ALL matching Next buttons simultaneously.
 *
 * @since 1.3.0
 * @since 1.7.0 Updated to handle both navigation systems coexisting.
 *
 * @return void
 */
function lde_inline_checkbox_script() {
	global $post;
	$post_id = ( $post ) ? $post->ID : 0;
	$nonce   = wp_create_nonce( 'lde_checkboxes' );
	?>
	<script>
		jQuery(function($){
			var ldePostId = <?php echo absint( $post_id ); ?>;
			var ldeNonce  = '<?php echo esc_js( $nonce ); ?>';
			var ldeAjax   = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';

			/**
			 * Remove stray <br> tags adjacent to checkbox labels.
			 * WordPress/Gutenberg wpautop inserts <br> between inline elements,
			 * which breaks the flex layout of checkbox + label pairs.
			 */
			$(
				'.ld-focus-content label, '
				+ '.ld-tab-bar__panel label, '
				+ '.ld-layout__content label, '
				+ '.learndash-wrapper label'
			).each(function(){
				var label = $(this);
				if (label.find('input[type="checkbox"]').length) {
					label.parent('p').find('br').remove();
				}
			});

			/**
			 * Scopes checkbox detection to the LearnDash content area only,
			 * avoiding interference with admin bars, widgets, or other plugins.
			 */
			function getContentCheckboxes() {
				var container = $(
					'.ld-focus-content, '
					+ '.ld-tab-bar__panel, '
					+ '.ld-layout__content, '
					+ '.learndash-wrapper'
				);
				if (container.length) {
					return container.find('input[type="checkbox"]');
				}
				return $('input[type="checkbox"]');
			}

			function checkCheckboxes() {
				var checkboxes = getContentCheckboxes();
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

			/**
			 * Finds ALL "Next" navigation buttons on the page.
			 * In Focus Mode both Legacy and Modern may be present simultaneously.
			 * Returns a jQuery collection of all matching elements.
			 */
			function getAllNextButtons() {
				var buttons = $();

				/* LD 5.0 Modern / Breezy: <a class="ld-navigation__next-link"> */
				buttons = buttons.add( $('.ld-navigation__next-link') );

				/* LD 4.x Legacy: <a class="ld-button"> inside .ld-content-actions */
				var legacy = $('.ld-content-actions .ld-button').filter(function(){
					var txt = $(this).find('.ld-text').text().trim().toLowerCase();
					return txt.includes('<?php echo esc_js( strtolower( esc_html__( 'next topic', 'learndash-evidence' ) ) ); ?>')
						|| txt.includes('<?php echo esc_js( strtolower( esc_html__( 'next lesson', 'learndash-evidence' ) ) ); ?>')
						|| txt.includes('<?php echo esc_js( strtolower( esc_html__( 'next quiz', 'learndash-evidence' ) ) ); ?>')
						|| txt.includes('siguiente')
						|| txt.includes('next');
				});
				buttons = buttons.add( legacy );

				return buttons;
			}

			function toggleNextButtons() {
				var buttons = getAllNextButtons();
				if (!buttons.length) return;

				buttons.each(function(){
					var btn = $(this);

					/* Store original href on first run so we can restore it. */
					if (typeof btn.data('lde-href') === 'undefined' && btn.attr('href')) {
						btn.data('lde-href', btn.attr('href'));
					}

					if (checkCheckboxes()) {
						/* Re-enable: restore href and remove disabled styles. */
						var savedHref = btn.data('lde-href');
						if (savedHref) {
							btn.attr('href', savedHref);
						}
						btn
							.prop('disabled', false)
							.attr('aria-disabled', 'false')
							.removeClass('ld-disabled ld-navigation__next-link--disabled')
							.css({ 'pointer-events': '', 'opacity': '' });
					} else {
						/* Disable: remove href and apply disabled styles. */
						btn
							.removeAttr('href')
							.prop('disabled', true)
							.attr('aria-disabled', 'true')
							.addClass('ld-disabled ld-navigation__next-link--disabled')
							.css({ 'pointer-events': 'none', 'opacity': '0.5' });
					}
				});
			}

			/**
			 * Tracks whether mark_complete has already been fired
			 * to avoid duplicate AJAX calls.
			 */
			var ldeAlreadyCompleted = false;

			/**
			 * Saves the current checkbox state to the server via AJAX.
			 * When all checkboxes are checked, also marks the topic as complete.
			 */
			function saveCheckboxState() {
				if ( ! ldePostId ) return;

				var checkboxes = getContentCheckboxes();
				var checked = [];

				checkboxes.each(function( index ) {
					if ( $(this).is(':checked') ) {
						checked.push( index );
					}
				});

				$.post( ldeAjax, {
					action:  'lde_save_checkboxes',
					nonce:   ldeNonce,
					post_id: ldePostId,
					checked: JSON.stringify( checked )
				});

				/* When ALL checkboxes are checked, mark the topic as complete. */
				if ( checkboxes.length > 0 && checked.length === checkboxes.length && ! ldeAlreadyCompleted ) {
					ldeAlreadyCompleted = true;
					$.post( ldeAjax, {
						action:  'lde_mark_complete',
						nonce:   ldeNonce,
						post_id: ldePostId
					});
				}
			}

			/**
			 * Loads saved checkbox state from the server and restores it.
			 * If all checkboxes were already checked, sets ldeAlreadyCompleted
			 * to avoid firing mark_complete again.
			 */
			function loadCheckboxState() {
				if ( ! ldePostId ) return;

				$.post( ldeAjax, {
					action:  'lde_load_checkboxes',
					nonce:   ldeNonce,
					post_id: ldePostId
				}, function( response ) {
					if ( response.success && response.data.checked.length ) {
						var checkboxes = getContentCheckboxes();
						$.each( response.data.checked, function( _, idx ) {
							if ( checkboxes[ idx ] ) {
								$( checkboxes[ idx ] ).prop( 'checked', true );
							}
						});

						/* If all were already checked, mark as already completed. */
						if ( checkCheckboxes() ) {
							ldeAlreadyCompleted = true;
						}

						toggleNextButtons();
					}
				});
			}

			/* Initial load: restore saved state, then apply button gating. */
			loadCheckboxState();
			toggleNextButtons();

			/* On every checkbox change: save state and update buttons. */
			$(document).on('change', 'input[type="checkbox"]', function(){
				toggleNextButtons();
				saveCheckboxState();
			});
		});
	</script>
	<?php
}
