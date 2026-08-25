<?php
/**
 * Notices
 *
 * @package     ArrayPress\RegisterNotices
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       2.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterNotices;

/**
 * Admin notices, declared once and shown when they apply.
 *
 * Printing a notice is three lines and needs no library. Two things around it
 * are not, and are what everybody reimplements badly:
 *
 * - **Showing one after a redirect.** An action that saves something has to
 *   redirect before it renders, so the message about what happened belongs to
 *   a different request from the work. That means a query argument, and
 *   reading it back, and taking it out of the URL afterwards.
 *
 * - **Dismissal that sticks.** Core draws the X and hides the notice; it does
 *   not remember. A notice that comes back on the next page load is one people
 *   learn to ignore.
 *
 * What this deliberately does not do any more: guess a notice's type from its
 * key. `licence_expired` inferred "success" because it did not end in
 * `_error`, which is the sort of magic that is right often enough to be
 * trusted and wrong exactly when it matters.
 */
final class Notices {

	/**
	 * Registered notices, by context and key.
	 *
	 * @var array<string, array<string, array<string, mixed>>>
	 */
	private static array $notices = [];

	/**
	 * Contexts that have had their hooks attached.
	 *
	 * @var array<string, bool>
	 */
	private static array $hooked = [];

	/**
	 * The query argument a notice is triggered by.
	 */
	public const TRIGGER = 'notice';

	/**
	 * Register a notice.
	 *
	 * @param string               $context The plugin's own namespace for these.
	 * @param string               $key     How the notice is triggered and remembered.
	 * @param array<string, mixed> $config  Its configuration.
	 *
	 * @return bool
	 */
	public static function add( string $context, string $key, array $config ): bool {
		$context = sanitize_key( $context );
		$key     = sanitize_key( $key );

		if ( '' === $context || '' === $key ) {
			return false;
		}

		$config = array_merge(
			[
				'message'     => '',
				'type'        => 'info',
				'dismissible' => true,
				'persistent'  => false,
				'capability'  => 'manage_options',
				'screens'     => [],
				'condition'   => null,
			],
			$config
		);

		if ( '' === (string) $config['message'] ) {
			return false;
		}

		// Core's four. Anything else renders as a notice with no colour,
		// which reads as a notice that failed rather than one of a kind
		// nobody has heard of.
		if ( ! in_array( $config['type'], [ 'success', 'warning', 'error', 'info' ], true ) ) {
			$config['type'] = 'info';
		}

		self::$notices[ $context ][ $key ] = $config;

		if ( ! isset( self::$hooked[ $context ] ) ) {
			self::$hooked[ $context ] = true;

			add_action( 'admin_notices', static fn() => self::render( $context ) );
			add_action( 'wp_ajax_dismiss_notice_' . $context, static fn() => self::dismiss( $context ) );
		}

		return true;
	}

	/**
	 * Whether a notice should be shown on this request.
	 *
	 * @param string               $context The context.
	 * @param string               $key     The notice's key.
	 * @param array<string, mixed> $config  Its configuration.
	 *
	 * @return bool
	 */
	public static function applies( string $context, string $key, array $config ): bool {
		if ( ! current_user_can( (string) $config['capability'] ) ) {
			return false;
		}

		if ( [] !== (array) $config['screens'] && ! self::on_screen( (array) $config['screens'] ) ) {
			return false;
		}

		if ( is_callable( $config['condition'] ) && ! call_user_func( $config['condition'] ) ) {
			return false;
		}

		if ( $config['dismissible'] && self::dismissed( $context, $key ) ) {
			return false;
		}

		// A persistent notice shows whenever everything above is true. Any
		// other kind waits to be triggered, which is what makes it the
		// message about something that just happened.
		return $config['persistent'] || self::triggered( $key );
	}

	/**
	 * Print whatever applies.
	 *
	 * @param string $context The context.
	 *
	 * @return void
	 */
	public static function render( string $context ): void {
		$shown = false;

		foreach ( self::$notices[ $context ] ?? [] as $key => $config ) {
			if ( ! self::applies( $context, $key, $config ) ) {
				continue;
			}

			printf(
				'<div class="notice notice-%s%s" data-notice-context="%s" data-notice-key="%s"><p>%s</p></div>',
				esc_attr( (string) $config['type'] ),
				$config['dismissible'] ? ' is-dismissible' : '',
				esc_attr( $context ),
				esc_attr( $key ),
				wp_kses_post( (string) $config['message'] )
			);

			$shown = $shown || (bool) $config['dismissible'];
		}

		// Only when there is something to remember. A script on every admin
		// page for a notice that is not there is a script nobody asked for.
		if ( $shown ) {
			self::script( $context );
		}
	}

	/**
	 * Remember that this user dismissed a notice.
	 *
	 * @param string $context The context.
	 *
	 * @return void
	 */
	public static function dismiss( string $context ): void {
		check_ajax_referer( 'dismiss_notice_' . $context );

		$key     = sanitize_key( $_POST['key'] ?? '' );
		$user_id = get_current_user_id();

		// A registered key only. Otherwise anyone signed in can write
		// unbounded rows of user meta by asking to dismiss notices that do
		// not exist.
		if ( '' === $key || 0 === $user_id || ! isset( self::$notices[ $context ][ $key ] ) ) {
			wp_die( '0', '', [ 'response' => 400 ] );
		}

		update_user_meta( $user_id, self::meta_key( $context, $key ), time() );

		wp_die( '1' );
	}

	/**
	 * Whether this user has dismissed a notice.
	 *
	 * @param string $context The context.
	 * @param string $key     The notice's key.
	 *
	 * @return bool
	 */
	public static function dismissed( string $context, string $key ): bool {
		$user_id = get_current_user_id();

		if ( 0 === $user_id ) {
			return false;
		}

		return '' !== (string) get_user_meta( $user_id, self::meta_key( $context, $key ), true );
	}

	/**
	 * Undo a dismissal, so the notice can be shown again.
	 *
	 * @param string   $context The context.
	 * @param string   $key     The notice's key.
	 * @param int|null $user_id Whose. The current user by default.
	 *
	 * @return bool
	 */
	public static function undismiss( string $context, string $key, ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();

		return 0 !== $user_id && delete_user_meta( $user_id, self::meta_key( $context, $key ) );
	}

	/**
	 * The URL that triggers a notice.
	 *
	 * What an action redirects to when it has finished.
	 *
	 * @param string $key The notice's key.
	 * @param string $url Where to go. The current screen by default.
	 *
	 * @return string
	 */
	public static function url( string $key, string $url = '' ): string {
		return add_query_arg( self::TRIGGER, sanitize_key( $key ), $url );
	}

	/**
	 * Whether the request asked for this notice.
	 *
	 * @param string $key The notice's key.
	 *
	 * @return bool
	 */
	private static function triggered( string $key ): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET[ self::TRIGGER ] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			&& sanitize_key( wp_unslash( $_GET[ self::TRIGGER ] ) ) === $key;
	}

	/**
	 * Whether the current screen is one of a list.
	 *
	 * @param array<int, string> $screens Screen ids.
	 *
	 * @return bool
	 */
	private static function on_screen( array $screens ): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		return $screen && in_array( $screen->id, $screens, true );
	}

	/**
	 * Where a dismissal is remembered.
	 *
	 * @param string $context The context.
	 * @param string $key     The notice's key.
	 *
	 * @return string
	 */
	private static function meta_key( string $context, string $key ): string {
		return sprintf( '_dismissed_notice_%s_%s', $context, $key );
	}

	/**
	 * The script that makes a dismissal stick.
	 *
	 * Core draws the X and hides the notice; it does not remember. This
	 * hears the click core already fires and posts it. Twenty lines rather
	 * than the hundred and forty that were here — the rest drew progress
	 * bars for notices that dismissed themselves on a timer, which is a
	 * message the reader may not have finished.
	 *
	 * @param string $context The context.
	 *
	 * @return void
	 */
	private static function script( string $context ): void {
		printf(
			'<script>(function(){document.addEventListener("click",function(e){' .
			'var b=e.target.closest(".notice-dismiss");if(!b)return;' .
			'var n=b.closest("[data-notice-key]");if(!n||n.dataset.noticeContext!==%s)return;' .
			'var d=new FormData();d.append("action","dismiss_notice_"+n.dataset.noticeContext);' .
			'd.append("key",n.dataset.noticeKey);d.append("_wpnonce",%s);' .
			'fetch(%s,{method:"POST",body:d,credentials:"same-origin"});});})();</script>',
			wp_json_encode( $context ),
			wp_json_encode( wp_create_nonce( 'dismiss_notice_' . $context ) ),
			wp_json_encode( admin_url( 'admin-ajax.php' ) )
		);
	}

	/**
	 * Everything registered for a context.
	 *
	 * @param string $context The context.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all( string $context ): array {
		return self::$notices[ $context ] ?? [];
	}
}
