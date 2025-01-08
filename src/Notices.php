<?php
/**
 * Admin Notices Registration Manager
 *
 * @package     ArrayPress\WP\Register
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\WP\Register;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Class Notices
 *
 * Manages WordPress admin notice registration and display.
 *
 * @since 1.0.0
 */
class Notices {

	/**
	 * Instance of this class.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Collection of registered notices
	 *
	 * @var array
	 */
	private array $registered_notices = [];

	/**
	 * Collection of active notices to display
	 *
	 * @var array
	 */
	private array $active_notices = [];

	/**
	 * Option prefix for storing notice data
	 *
	 * @var string
	 */
	private string $prefix = '';

	/**
	 * Debug mode status
	 *
	 * @var bool
	 */
	private bool $debug = false;

	/**
	 * Get instance of this class.
	 *
	 * @return self Instance of this class.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor
	 *

	 */
	private function __construct() {
		$this->debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
		add_action( 'admin_notices', [ $this, 'process_notices' ] );
		add_action( 'admin_notices', [ $this, 'display_notices' ] );
		add_action( 'admin_init', [ $this, 'handle_notice_dismissal' ] );
	}

	/**
	 * Set the prefix
	 *
	 * @param string $prefix The prefix to use
	 *
	 * @return self
	 */
	public function set_prefix( string $prefix ): self {
		$this->prefix = $prefix;

		return $this;
	}

	/**
	 * Register notices
	 *
	 * @param array $notices Array of notices configurations
	 *
	 * @return self
	 */
	public function register( array $notices ): self {
		foreach ( $notices as $key => $notice ) {
			if ( ! isset( $notice['message'] ) ) {
				$this->log( sprintf( 'Notice %s missing message configuration', $key ) );
				continue;
			}

			// Store with defaults
			$this->registered_notices[ $key ] = wp_parse_args( $notice, [
				'message'        => '',
				'class'          => 'updated',
				'is_dismissible' => true,
				'capability'     => '',
				'conditions'     => [] // Additional display conditions
			] );
		}

		return $this;
	}

	/**
	 * Process notices based on query args
	 *
	 * @return void
	 */
	public function process_notices(): void {
		// Check for notice trigger
		$notice_key = filter_input( INPUT_GET, $this->prefix . '-notice', FILTER_SANITIZE_STRING );
		if ( empty( $notice_key ) ) {
			return;
		}

		// Find registered notice
		if ( ! isset( $this->registered_notices[ $notice_key ] ) ) {
			$this->log( sprintf( 'Notice %s not found in registry', $notice_key ) );

			return;
		}

		$notice = $this->registered_notices[ $notice_key ];

		// Check capability
		if ( ! empty( $notice['capability'] ) && ! current_user_can( $notice['capability'] ) ) {
			return;
		}

		// Check conditions
		if ( ! empty( $notice['conditions'] ) && is_callable( $notice['conditions'] ) ) {
			if ( ! call_user_func( $notice['conditions'] ) ) {
				return;
			}
		}

		// Check if dismissed
		if ( $notice['is_dismissible'] && $this->is_dismissed( $notice_key ) ) {
			return;
		}

		// Process message - might be string, array, or callback
		$message = $this->process_message( $notice['message'] );
		if ( false === $message ) {
			return;
		}

		// Add notice to active queue
		$this->active_notices[ $notice_key ] = [
			'message'        => $message,
			'class'          => $notice['class'],
			'is_dismissible' => $notice['is_dismissible']
		];
	}

	/**
	 * Process message content
	 *
	 * @param mixed $message Message to process
	 *
	 * @return string|false
	 */
	protected function process_message( $message ) {
		// Direct string
		if ( is_string( $message ) ) {
			return '<p>' . wp_kses_post( $message ) . '</p>';
		}

		// Array of messages
		if ( is_array( $message ) ) {
			return '<p>' . implode( '</p><p>', array_map( 'wp_kses_post', $message ) ) . '</p>';
		}

		// Callback function
		if ( is_callable( $message ) ) {
			return call_user_func( $message );
		}

		// WP_Error object
		if ( is_wp_error( $message ) ) {
			$errors = $message->get_error_messages();
			if ( empty( $errors ) ) {
				return false;
			}

			if ( count( $errors ) === 1 ) {
				return '<p>' . wp_kses_post( $errors[0] ) . '</p>';
			}

			return '<ul><li>' . implode( '</li><li>', array_map( 'wp_kses_post', $errors ) ) . '</li></ul>';
		}

		return false;
	}

	/**
	 * Display active notices
	 *
	 * @return void
	 */
	public function display_notices(): void {
		if ( empty( $this->active_notices ) ) {
			return;
		}

		foreach ( $this->active_notices as $id => $notice ) {
			$classes = [ 'notice', $notice['class'] ];
			if ( $notice['is_dismissible'] ) {
				$classes[] = 'is-dismissible';
			}

			printf(
				'<div class="%1$s" data-notice-id="%2$s">%3$s</div>',
				esc_attr( implode( ' ', $classes ) ),
				esc_attr( $id ),
				$notice['message']
			);

			if ( $notice['is_dismissible'] ) {
				$this->add_dismissible_script( $id );
			}
		}
	}

	/**
	 * Add dismissible notice script
	 *
	 * @param string $notice_id Notice ID
	 *
	 * @return void
	 */
	protected function add_dismissible_script( string $notice_id ): void {
		$dismiss_url = wp_nonce_url(
			add_query_arg(
				[
					'action' => 'dismiss_admin_notice',
					'notice' => $notice_id,
					'prefix' => $this->prefix
				]
			),
			'dismiss_notice_' . $notice_id
		);

		printf(
			'<script>jQuery(document).ready(function($) {
                $(".notice[data-notice-id=\'%1$s\']").on("click", ".notice-dismiss", function() {
                    $.get("%2$s");
                });
            });</script>',
			esc_attr( $notice_id ),
			esc_url( $dismiss_url )
		);
	}

	/**
	 * Handle notice dismissal
	 *
	 * @return void
	 */
	public function handle_notice_dismissal(): void {
		$action = filter_input( INPUT_GET, 'action', FILTER_SANITIZE_STRING );
		if ( 'dismiss_admin_notice' !== $action ) {
			return;
		}

		$notice = filter_input( INPUT_GET, 'notice', FILTER_SANITIZE_STRING );
		$prefix = filter_input( INPUT_GET, 'prefix', FILTER_SANITIZE_STRING );
		$nonce  = filter_input( INPUT_GET, '_wpnonce', FILTER_SANITIZE_STRING );

		if ( ! $notice || ! $prefix || ! $nonce ) {
			return;
		}

		if ( ! wp_verify_nonce( $nonce, 'dismiss_notice_' . $notice ) ) {
			return;
		}

		$this->dismiss_notice( $notice );
		wp_die();
	}

	/**
	 * Dismiss a notice
	 *
	 * @param string $notice_id Notice ID
	 *
	 * @return void
	 */
	protected function dismiss_notice( string $notice_id ): void {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		update_user_meta( $user_id, $this->get_dismiss_key( $notice_id ), true );
	}

	/**
	 * Check if notice is dismissed
	 *
	 * @param string $notice_id Notice ID
	 *
	 * @return bool
	 */
	protected function is_dismissed( string $notice_id ): bool {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		return (bool) get_user_meta( $user_id, $this->get_dismiss_key( $notice_id ), true );
	}

	/**
	 * Get dismiss meta key
	 *
	 * @param string $notice_id Notice ID
	 *
	 * @return string
	 */
	protected function get_dismiss_key( string $notice_id ): string {
		return sprintf( '_%s_notice_%s_dismissed', $this->prefix, $notice_id );
	}

	/**
	 * Log debug message
	 *
	 * @param string $message Message to log
	 * @param array  $context Optional context
	 *
	 * @return void
	 */
	protected function log( string $message, array $context = [] ): void {
		if ( $this->debug ) {
			error_log( sprintf(
				'[%s] Notices: %s %s',
				$this->prefix,
				$message,
				$context ? json_encode( $context ) : ''
			) );
		}
	}

}