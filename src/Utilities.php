<?php
/**
 * Admin Notices Registration Helper Functions
 *
 * @package     ArrayPress\WP\Register
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

use ArrayPress\WP\Register\Notices;

if ( ! function_exists( 'register_admin_notices' ) ):
	/**
	 * Register multiple WordPress admin notices at once.
	 *
	 * @param array  $notices Array of notice configurations
	 * @param string $prefix  Optional prefix for notice identification
	 *
	 * @return Notices Instance of Notices class for method chaining
	 */
	function register_admin_notices( array $notices, string $prefix = '' ): Notices {
		return Notices::instance()
		              ->set_prefix( $prefix )
		              ->register( $notices );
	}
endif;

if ( ! function_exists( 'add_admin_notice' ) ):
	/**
	 * Add a custom admin notice with specified configuration.
	 *
	 * @param string $message The notice message
	 * @param string $id      Unique identifier for the notice
	 * @param string $type    Notice type: 'info', 'success', 'warning', 'error'
	 * @param array  $args    Optional arguments for the notice
	 * @param string $prefix  Optional prefix for notice identification
	 *
	 * @return void
	 */
	function add_admin_notice(
		string $message,
		string $id,
		string $type = 'info',
		array $args = [],
		string $prefix = ''
	): void {
		$notice_classes = [
			'info'    => 'notice-info',
			'success' => 'updated',
			'warning' => 'notice-warning',
			'error'   => 'notice-error'
		];

		$defaults = [
			'message'        => $message,
			'class'          => $notice_classes[ $type ] ?? 'notice-info',
			'is_dismissible' => true,
			'capability'     => '',
			'conditions'     => null
		];

		$notice = wp_parse_args( $args, $defaults );

		Notices::instance()
		       ->set_prefix( $prefix )
		       ->register( [ $id => $notice ] );
	}
endif;

if ( ! function_exists( 'add_info_notice' ) ):
	/**
	 * Add an informational notice.
	 *
	 * @param string $message The notice message
	 * @param string $id      Unique identifier for the notice
	 * @param array  $args    Optional arguments for the notice
	 * @param string $prefix  Optional prefix for notice identification
	 *
	 * @return void
	 */
	function add_info_notice( string $message, string $id, array $args = [], string $prefix = '' ): void {
		add_admin_notice( $message, $id, 'info', $args, $prefix );
	}
endif;

if ( ! function_exists( 'add_success_notice' ) ):
	/**
	 * Add a success notice.
	 *
	 * @param string $message The success message
	 * @param string $id      Unique identifier for the notice
	 * @param array  $args    Optional arguments for the notice
	 * @param string $prefix  Optional prefix for notice identification
	 *
	 * @return void
	 */
	function add_success_notice( string $message, string $id, array $args = [], string $prefix = '' ): void {
		add_admin_notice( $message, $id, 'success', $args, $prefix );
	}
endif;

if ( ! function_exists( 'add_warning_notice' ) ):
	/**
	 * Add a warning notice.
	 *
	 * @param string $message The warning message
	 * @param string $id      Unique identifier for the notice
	 * @param array  $args    Optional arguments for the notice
	 * @param string $prefix  Optional prefix for notice identification
	 *
	 * @return void
	 */
	function add_warning_notice( string $message, string $id, array $args = [], string $prefix = '' ): void {
		add_admin_notice( $message, $id, 'warning', $args, $prefix );
	}
endif;

if ( ! function_exists( 'add_error_notice' ) ):
	/**
	 * Add an error notice.
	 *
	 * @param string|WP_Error $message The error message or WP_Error object
	 * @param string          $id      Unique identifier for the notice
	 * @param array           $args    Optional arguments for the notice
	 * @param string          $prefix  Optional prefix for notice identification
	 *
	 * @return void
	 */
	function add_error_notice( $message, string $id, array $args = [], string $prefix = '' ): void {
		add_admin_notice( $message, $id, 'error', $args, $prefix );
	}
endif;