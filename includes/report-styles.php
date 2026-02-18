<?php
/**
 * Report styles and print support for LearnDash Evidence.
 *
 * Outputs CSS for the evidence report, optimized for screen display
 * and A4 print / PDF export.
 *
 * @package learndash-evidence
 * @since   1.6.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Outputs inline CSS for the evidence report.
 *
 * @since 1.3.0
 *
 * @return void
 */
function lde_report_styles() {
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

/**
 * Outputs inline JS for auto-print when #print hash is present.
 *
 * @since 1.3.0
 *
 * @return void
 */
function lde_autoprint_script() {
	echo '<script>window.onload=function(){if(location.hash==="#print")window.print();}</script>';
}
