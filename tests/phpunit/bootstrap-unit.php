<?php
/**
 * Bootstrap for standalone unit tests.
 *
 * These tests exercise the pure parsing / conversion logic in isolation —
 * no WordPress, no database and no network. A few WordPress functions used by
 * the classes are stubbed minimally so the code under test can run.
 *
 * @package NExT_FC2Blog2WP
 */

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * Minimal stub of WordPress' esc_attr().
	 *
	 * @param string $text Text to escape.
	 * @return string
	 */
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

require_once dirname( __DIR__, 2 ) . '/class/fc2_html_parser.php';
require_once dirname( __DIR__, 2 ) . '/class/next-fc2blog2wp_class.php';
