<?php
/**
 * PHPUnit bootstrap.
 *
 * @package ArrayPress\RegisterNotices
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['notices_hooks'][ $hook ][] = $callback;

		return true;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( (string) preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $text ) {
		return strip_tags( (string) $text, '<a><strong><em><code><br>' );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value, $flags = 0, $depth = 512 ) {
		return json_encode( $value, $flags, $depth );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $key, $value = '', $url = '' ) {
		$url = (string) $url;

		return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . $key . '=' . rawurlencode( (string) $value );
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) {
		return 'nonce-' . $action;
	}
}

/*
 * Who the current user is and what they may do. Null caps means everything,
 * so a test that is not about permissions does not have to care.
 */
if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return (int) ( $GLOBALS['notices_user'] ?? 1 );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability, ...$args ) {
		$allowed = $GLOBALS['notices_caps'] ?? null;

		return null === $allowed || in_array( $capability, (array) $allowed, true );
	}
}

if ( ! function_exists( 'get_user_meta' ) ) {
	function get_user_meta( $user_id, $key = '', $single = false ) {
		return $GLOBALS['notices_meta'][ $user_id ][ $key ] ?? '';
	}
}

if ( ! function_exists( 'update_user_meta' ) ) {
	function update_user_meta( $user_id, $key, $value, $prev = '' ) {
		$GLOBALS['notices_meta'][ $user_id ][ $key ] = $value;

		return true;
	}
}

if ( ! function_exists( 'delete_user_meta' ) ) {
	function delete_user_meta( $user_id, $key, $value = '' ) {
		$existed = isset( $GLOBALS['notices_meta'][ $user_id ][ $key ] );

		unset( $GLOBALS['notices_meta'][ $user_id ][ $key ] );

		return $existed;
	}
}

if ( ! function_exists( 'get_current_screen' ) ) {
	function get_current_screen() {
		return $GLOBALS['notices_screen'] ?? null;
	}
}

/**
 * Forget everything a previous test set up.
 *
 * @return void
 */
function notices_reset_globals(): void {
	$GLOBALS['notices_hooks']  = [];
	$GLOBALS['notices_meta']   = [];
	$GLOBALS['notices_screen'] = null;
	$GLOBALS['notices_user']   = 1;
	$GLOBALS['notices_caps']   = null;

	$_GET  = [];
	$_POST = [];

	foreach ( [ 'notices', 'hooked' ] as $property ) {
		( new ReflectionProperty( 'ArrayPress\RegisterNotices\Notices', $property ) )->setValue( null, [] );
	}
}
