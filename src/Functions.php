<?php
/**
 * Admin Notices Helper Functions
 *
 * Global helper functions for the admin notices system.
 *
 * @package     ArrayPress\WP\Utils
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

use ArrayPress\RegisterNotices\Manager;

if ( ! function_exists( 'register_admin_notices' ) ) {
	/**
	 * Register admin notices with a declarative configuration
	 *
	 * Creates a new AdminNotices instance and registers the provided notices.
	 *
	 * @param string $context Unique context/namespace for this notice group
	 * @param array  $notices Array of notice configurations
	 * @param array  $options Global options for all notices in this group
	 *
	 * @return Manager The notices instance for further manipulation if needed
	 * @since 1.0.0
	 *
	 */
	function register_admin_notices( string $context, array $notices, array $options = [] ): Manager {
		$instance = new Manager( $context, $options );
		$instance->register( $notices );

		return $instance;
	}
}