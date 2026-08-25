<?php
/**
 * Registration
 *
 * @package     ArrayPress\RegisterNotices
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       2.0.0
 */

declare( strict_types=1 );

use ArrayPress\RegisterNotices\Notices;

if ( ! function_exists( 'register_admin_notices' ) ) {
	/**
	 * Register a plugin's admin notices.
	 *
	 *     register_admin_notices( 'myplugin', [
	 *         'settings_saved' => [
	 *             'message' => __( 'Settings saved.', 'my-plugin' ),
	 *             'type'    => 'success',
	 *         ],
	 *         'needs_api_key' => [
	 *             'message'    => __( 'Add an API key to start syncing.', 'my-plugin' ),
	 *             'type'       => 'warning',
	 *             'persistent' => true,
	 *             'condition'  => fn(): bool => ! get_option( 'myplugin_api_key' ),
	 *         ],
	 *     ] );
	 *
	 * @param string                              $context The plugin's namespace for these.
	 * @param array<string, array<string, mixed>> $notices Notices, by key.
	 *
	 * @return int How many registered.
	 */
	function register_admin_notices( string $context, array $notices ): int {
		$registered = 0;

		foreach ( $notices as $key => $config ) {
			if ( Notices::add( $context, (string) $key, (array) $config ) ) {
				++$registered;
			}
		}

		return $registered;
	}
}

if ( ! function_exists( 'admin_notice_url' ) ) {
	/**
	 * The URL that shows a notice.
	 *
	 * What an action redirects to when it has finished:
	 *
	 *     wp_safe_redirect( admin_notice_url( 'settings_saved' ) );
	 *
	 * @param string $key The notice's key.
	 * @param string $url Where to go. The current screen by default.
	 *
	 * @return string
	 */
	function admin_notice_url( string $key, string $url = '' ): string {
		return Notices::url( $key, $url );
	}
}

if ( ! function_exists( 'admin_notice_dismissed' ) ) {
	/**
	 * Whether this user has dismissed a notice.
	 *
	 * @param string $context The context.
	 * @param string $key     The notice's key.
	 *
	 * @return bool
	 */
	function admin_notice_dismissed( string $context, string $key ): bool {
		return Notices::dismissed( $context, $key );
	}
}

if ( ! function_exists( 'reset_admin_notice' ) ) {
	/**
	 * Undo a dismissal, so the notice can be shown again.
	 *
	 * @param string   $context The context.
	 * @param string   $key     The notice's key.
	 * @param int|null $user_id Whose. The current user by default.
	 *
	 * @return bool
	 */
	function reset_admin_notice( string $context, string $key, ?int $user_id = null ): bool {
		return Notices::undismiss( $context, $key, $user_id );
	}
}
