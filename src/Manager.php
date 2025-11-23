<?php
/**
 * Admin Notices Manager
 *
 * A simplified, declarative API for managing WordPress admin notices.
 *
 * @package     ArrayPress\AdminNotices
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.1.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterNotices;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Class Manager
 *
 * Manages WordPress admin notice registration and display with a clean, declarative API.
 *
 * @since 1.0.0
 */
class Manager {

    /**
     * Unique context/namespace for this notice group
     *
     * @since 1.0.0
     * @var string
     */
    private string $context;

    /**
     * Registered notices for this context
     *
     * @since 1.0.0
     * @var array<string, array>
     */
    private array $notices = [];

    /**
     * Global options for this notice group
     *
     * @since 1.0.0
     * @var array
     */
    private array $options = [];

    /**
     * Active notices to display this request
     *
     * @since 1.0.0
     * @var array<string, array>
     */
    private array $active = [];

    /**
     * Whether hooks have been initialized
     *
     * @since 1.0.0
     * @var bool
     */
    private bool $initialized = false;

    /**
     * Constructor
     *
     * @param string      $context      Unique context/namespace for this notice group
     * @param array       $options      {
     *                                  Global options for all notices in this group
     *
     * @type array|string $pages        Default pages for all notices
     * @type string       $capability   Default capability for all notices
     * @type bool         $dismissible  Default dismissible state (default: true)
     * @type int          $auto_dismiss Default auto-dismiss time in milliseconds (default: 0)
     *                                  }
     * @since 1.0.0
     *
     */
    public function __construct( string $context, array $options = [] ) {
        $this->context = sanitize_key( $context );

        $defaults = [
                'pages'        => null,
                'capability'   => null,
                'dismissible'  => true,
                'auto_dismiss' => 0,
        ];

        $this->options = wp_parse_args( $options, $defaults );
    }

    /**
     * Register notices
     *
     * Registers admin notices with various configuration options.
     * Notices can be simple strings or complex configurations with callbacks.
     *
     * @param array          $notices      {
     *                                     Array of notice configurations. Can be:
     *                                     - Simple: 'key' => 'message'
     *                                     - Complex: 'key' => [ configuration array ]
     *
     * @type string|callable $message      Notice message or callback that returns message
     * @type string          $type         Notice type: 'success', 'error', 'warning', 'info'
     * @type string          $trigger      GET parameter that triggers this notice
     * @type array           $data         GET parameters to pass to message callback
     * @type callable        $condition    Callback to determine if notice should show
     * @type bool            $dismissible  Whether notice can be dismissed (default: true)
     * @type int             $auto_dismiss Auto-dismiss after milliseconds (0 = never)
     * @type bool            $persistent   Whether notice shows on every page load (default: false)
     * @type string          $capability   Required capability to see notice
     * @type array|string    $pages        Admin pages where notice can appear
     *                                     }
     * @return self Returns instance for method chaining
     * @since 1.0.0
     *
     */
    public function register( array $notices ): self {
        foreach ( $notices as $key => $notice ) {
            $this->notices[ $key ] = $this->normalize_notice( $key, $notice );
        }

        $this->init();

        return $this;
    }

    /**
     * Add a single notice
     *
     * Convenience method to add a single notice after initial registration.
     *
     * @param string       $key    Notice key/identifier
     * @param string|array $notice Notice message or configuration
     *
     * @return self Returns instance for method chaining
     * @since 1.0.0
     *
     */
    public function add( string $key, $notice ): self {
        $this->notices[ $key ] = $this->normalize_notice( $key, $notice );
        $this->init();

        return $this;
    }

    /**
     * Remove a notice
     *
     * Removes a previously registered notice.
     *
     * @param string $key Notice key to remove
     *
     * @return self Returns instance for method chaining
     * @since 1.0.0
     *
     */
    public function remove( string $key ): self {
        unset( $this->notices[ $key ] );

        return $this;
    }

    /**
     * Initialize hooks
     *
     * Sets up WordPress action hooks for notice display and dismissal handling.
     *
     * @return void
     * @since  1.0.0
     * @access private
     *
     */
    private function init(): void {
        if ( $this->initialized ) {
            return;
        }

        add_action( 'admin_init', [ $this, 'process_notices' ] );
        add_action( 'admin_notices', [ $this, 'display_notices' ] );
        add_action( 'wp_ajax_dismiss_admin_notice_' . $this->context, [ $this, 'handle_dismissal' ] );

        $this->initialized = true;
    }

    /**
     * Normalize notice configuration
     *
     * Converts various notice formats into a consistent structure.
     *
     * @param string $key    Notice key/identifier
     * @param mixed  $notice Notice configuration (string or array)
     *
     * @return array Normalized notice configuration
     * @since  1.0.0
     * @access private
     *
     */
    private function normalize_notice( string $key, $notice ): array {
        // Simple string message
        if ( is_string( $notice ) ) {
            $notice = [
                    'message' => $notice,
                    'trigger' => $key,
                    'type'    => $this->infer_type( $key )
            ];
        }

        // Apply defaults
        $defaults = [
                'message'      => '',
                'type'         => 'success',
                'trigger'      => $key,
                'data'         => [],
                'condition'    => null,
                'dismissible'  => $this->options['dismissible'],
                'auto_dismiss' => $this->options['auto_dismiss'],
                'persistent'   => false,
                'capability'   => $this->options['capability'],
                'pages'        => $this->options['pages'],
        ];

        $notice = wp_parse_args( $notice, $defaults );

        // Ensure data is always an array
        if ( ! empty( $notice['data'] ) && ! is_array( $notice['data'] ) ) {
            $notice['data'] = [ $notice['data'] ];
        }

        // Smart defaults for auto-dismiss based on type
        if ( $notice['auto_dismiss'] === 0 && $this->should_auto_dismiss_type( $notice['type'] ) ) {
            $notice['auto_dismiss'] = $this->get_auto_dismiss_time( $notice['type'] );
        }

        return $notice;
    }

    /**
     * Check if notice type should auto-dismiss by default
     *
     * @param string $type Notice type
     *
     * @return bool Whether type should auto-dismiss
     * @since  1.1.0
     * @access private
     *
     */
    private function should_auto_dismiss_type( string $type ): bool {
        // Only auto-dismiss success messages by default if explicitly configured
        return false; // Changed to false - make it opt-in only
    }

    /**
     * Get default auto-dismiss time for notice type
     *
     * @param string $type Notice type
     *
     * @return int Milliseconds before auto-dismiss
     * @since  1.1.0
     * @access private
     *
     */
    private function get_auto_dismiss_time( string $type ): int {
        $times = [
                'success' => 5000,  // 5 seconds
                'info'    => 7000,  // 7 seconds
                'warning' => 0,     // Don't auto-dismiss
                'error'   => 0,     // Never auto-dismiss
        ];

        return $times[ $type ] ?? 0;
    }

    /**
     * Infer notice type from key
     *
     * Attempts to determine notice type from the key name.
     * Looks for suffixes like _success, _error, _warning, _info.
     *
     * @param string $key Notice key
     *
     * @return string Notice type
     * @since  1.0.0
     * @access private
     *
     */
    private function infer_type( string $key ): string {
        if ( str_ends_with( $key, '_error' ) || str_starts_with( $key, 'error_' ) ) {
            return 'error';
        }
        if ( str_ends_with( $key, '_warning' ) || str_starts_with( $key, 'warning_' ) ) {
            return 'warning';
        }
        if ( str_ends_with( $key, '_info' ) || str_starts_with( $key, 'info_' ) ) {
            return 'info';
        }

        return 'success';
    }

    /**
     * Process notices for current request
     *
     * Checks all registered notices and determines which should be displayed
     * based on triggers, conditions, and page context.
     *
     * @return void
     * @since 1.0.0
     *
     */
    public function process_notices(): void {
        if ( empty( $this->notices ) ) {
            return;
        }

        $current_page = $_GET['page'] ?? '';

        foreach ( $this->notices as $key => $notice ) {
            // Check page context
            if ( ! $this->is_valid_page( $notice['pages'], $current_page ) ) {
                continue;
            }

            // Check capability
            if ( $notice['capability'] && ! current_user_can( $notice['capability'] ) ) {
                continue;
            }

            // Check if dismissed
            if ( $notice['dismissible'] && $this->is_dismissed( $key ) ) {
                continue;
            }

            // Process based on type
            if ( $notice['persistent'] && $notice['condition'] ) {
                // Persistent conditional notice
                if ( call_user_func( $notice['condition'] ) ) {
                    $this->active[ $key ] = $this->prepare_notice( $key, $notice );
                }
            } elseif ( $notice['condition'] ) {
                // Conditional notice (non-persistent)
                if ( call_user_func( $notice['condition'] ) ) {
                    $this->active[ $key ] = $this->prepare_notice( $key, $notice );
                }
            } elseif ( isset( $_GET[ $notice['trigger'] ] ) ) {
                // Triggered notice
                $this->active[ $key ] = $this->prepare_notice( $key, $notice, true );
            }
        }
    }

    /**
     * Prepare notice for display
     *
     * Processes the notice message, handling callbacks and data interpolation.
     *
     * @param string $key       Notice key
     * @param array  $notice    Notice configuration
     * @param bool   $triggered Whether notice was triggered by GET parameter
     *
     * @return array|null Prepared notice data or null if message generation fails
     * @since  1.0.0
     * @access private
     *
     */
    private function prepare_notice( string $key, array $notice, bool $triggered = false ): ?array {
        $message = $notice['message'];

        // Handle callable messages
        if ( is_callable( $message ) ) {
            $args = [];

            // Collect data from GET parameters
            if ( $triggered && ! empty( $notice['data'] ) ) {
                foreach ( $notice['data'] as $param ) {
                    if ( isset( $_GET[ $param ] ) ) {
                        $args[ $param ] = sanitize_text_field( $_GET[ $param ] );
                    }
                }
            }

            // Also pass the trigger value if it's not just a flag
            if ( $triggered && isset( $_GET[ $notice['trigger'] ] ) && $_GET[ $notice['trigger'] ] !== '1' ) {
                $args[ $notice['trigger'] ] = sanitize_text_field( $_GET[ $notice['trigger'] ] );
            }

            $message = call_user_func( $message, $args );
        }

        if ( empty( $message ) ) {
            return null;
        }

        return [
                'key'          => $key,
                'message'      => $message,
                'type'         => $notice['type'],
                'dismissible'  => $notice['dismissible'],
                'auto_dismiss' => $notice['auto_dismiss']
        ];
    }

    /**
     * Display active notices
     *
     * Renders all active notices with appropriate WordPress admin notice markup.
     *
     * @return void
     * @since 1.0.0
     *
     */
    public function display_notices(): void {
        if ( empty( $this->active ) ) {
            return;
        }

        static $script_added = false;

        foreach ( $this->active as $notice ) {
            if ( ! $notice ) {
                continue;
            }

            $classes = $this->get_notice_classes( $notice['type'], $notice['dismissible'] );

            // Add auto-dismiss class if needed
            if ( $notice['auto_dismiss'] > 0 ) {
                $classes .= ' wp-flyout-auto-dismiss';
            }

            // Build data attributes
            $data_attrs = [
                    'data-notice-context="' . esc_attr( $this->context ) . '"',
                    'data-notice-key="' . esc_attr( $notice['key'] ) . '"'
            ];

            if ( $notice['auto_dismiss'] > 0 ) {
                $data_attrs[] = 'data-auto-dismiss="' . esc_attr( (string) $notice['auto_dismiss'] ) . '"';
            }

            printf(
                    '<div class="%1$s" %2$s>%3$s</div>',
                    esc_attr( $classes ),
                    implode( ' ', $data_attrs ),
                    wp_kses_post( wpautop( $notice['message'] ) )
            );
        }

        // Add dismissal script once if we have dismissible or auto-dismiss notices
        if ( ! $script_added && ( $this->has_dismissible_notices() || $this->has_auto_dismiss_notices() ) ) {
            $this->add_scripts();
            $script_added = true;
        }
    }

    /**
     * Check if there are dismissible notices
     *
     * @return bool True if any active notice is dismissible
     * @since  1.0.0
     * @access private
     *
     */
    private function has_dismissible_notices(): bool {
        foreach ( $this->active as $notice ) {
            if ( $notice['dismissible'] ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if there are auto-dismiss notices
     *
     * @return bool True if any active notice has auto-dismiss
     * @since  1.1.0
     * @access private
     *
     */
    private function has_auto_dismiss_notices(): bool {
        foreach ( $this->active as $notice ) {
            if ( $notice['auto_dismiss'] > 0 ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get notice CSS classes
     *
     * Returns the appropriate WordPress admin notice CSS classes based on type.
     *
     * @param string $type        Notice type
     * @param bool   $dismissible Whether notice is dismissible
     *
     * @return string Space-separated CSS classes
     * @since  1.0.0
     * @access private
     *
     */
    private function get_notice_classes( string $type, bool $dismissible ): string {
        $classes = [ 'notice' ];

        $type_map = [
                'success' => 'notice-success',
                'error'   => 'notice-error',
                'warning' => 'notice-warning',
                'info'    => 'notice-info'
        ];

        $classes[] = $type_map[ $type ] ?? 'notice-success';

        if ( $dismissible ) {
            $classes[] = 'is-dismissible';
        }

        return implode( ' ', $classes );
    }

    /**
     * Add dismissal and auto-dismiss JavaScript with styles
     *
     * @return void
     * @since  1.1.0
     * @access private
     *
     */
    private function add_scripts(): void {
        $nonce = wp_create_nonce( 'dismiss_notice_' . $this->context );
        ?>
        <style>
            .notice.wp-flyout-auto-dismiss {
                position: relative;
                overflow: hidden;
            }

            .notice.wp-flyout-auto-dismiss .notice-dismiss-progress {
                position: absolute;
                bottom: 0;
                left: 0;
                height: 3px;
                background: currentColor;
                opacity: 0.2;
                transition: width linear;
                width: 100%;
            }

            .notice.notice-success .notice-dismiss-progress {
                background-color: #00a32a;
            }

            .notice.notice-info .notice-dismiss-progress {
                background-color: #72aee6;
            }

            .notice.notice-warning .notice-dismiss-progress {
                background-color: #dba617;
            }

            .notice.notice-error .notice-dismiss-progress {
                background-color: #d63638;
            }

            .notice.wp-flyout-auto-dismiss.paused .notice-dismiss-progress {
                transition: none !important;
            }
        </style>
        <script>
            jQuery(function ($) {
                // Regular dismissal handler
                $(document).on('click', '.notice[data-notice-context="<?php echo esc_js( $this->context ); ?>"] .notice-dismiss', function () {
                    var $notice = $(this).closest('.notice');
                    var key = $notice.attr('data-notice-key');
                    if (key) {
                        $.post(ajaxurl, {
                            action: 'dismiss_admin_notice_<?php echo esc_js( $this->context ); ?>',
                            key: key,
                            _wpnonce: '<?php echo esc_js( $nonce ); ?>'
                        });
                    }
                });

                // Auto-dismiss notices with visual progress
                $('.notice[data-auto-dismiss]').each(function () {
                    var $notice = $(this);
                    var delay = parseInt($notice.attr('data-auto-dismiss')) || 5000;
                    var timer;
                    var isPaused = false;

                    // Add progress bar
                    var $progress = $('<div class="notice-dismiss-progress"></div>').appendTo($notice);

                    // Start the countdown
                    function startTimer() {
                        // Animate progress bar to 0
                        setTimeout(function () {
                            if (!isPaused) {
                                $progress.css({
                                    'transition-duration': (delay / 1000) + 's',
                                    'width': '0%'
                                });
                            }
                        }, 10);

                        // Set timer to remove notice
                        timer = setTimeout(function () {
                            if (!isPaused) {
                                $notice.fadeOut(400, function () {
                                    $notice.remove();
                                });
                            }
                        }, delay);
                    }

                    // Pause on hover
                    $notice.on('mouseenter', function () {
                        isPaused = true;
                        $notice.addClass('paused');
                        clearTimeout(timer);

                        // Stop the progress bar animation
                        var currentWidth = $progress[0].offsetWidth;
                        $progress.css({
                            'transition-duration': '0s',
                            'width': currentWidth + 'px'
                        });
                    });

                    // Resume on mouse leave
                    $notice.on('mouseleave', function () {
                        isPaused = false;
                        $notice.removeClass('paused');

                        // Calculate remaining time based on progress bar width
                        var remainingPercent = $progress.width() / $notice.width();
                        var remainingTime = delay * remainingPercent;

                        // Resume progress bar animation
                        $progress.css({
                            'transition-duration': (remainingTime / 1000) + 's',
                            'width': '0%'
                        });

                        // Reset timer with remaining time
                        timer = setTimeout(function () {
                            if (!isPaused) {
                                $notice.fadeOut(400, function () {
                                    $notice.remove();
                                });
                            }
                        }, remainingTime);
                    });

                    // Start the initial timer
                    startTimer();
                });
            });
        </script>
        <?php
    }

    /**
     * Handle notice dismissal AJAX request
     *
     * Processes AJAX requests to permanently dismiss a notice for the current user.
     *
     * @return void
     * @since 1.0.0
     *
     */
    public function handle_dismissal(): void {
        check_ajax_referer( 'dismiss_notice_' . $this->context );

        $key = sanitize_key( $_POST['key'] ?? '' );
        if ( ! $key ) {
            wp_die( 0 );
        }

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            wp_die( 0 );
        }

        $meta_key = $this->get_dismiss_key( $key );
        update_user_meta( $user_id, $meta_key, time() );

        wp_die( 1 );
    }

    /**
     * Check if notice is dismissed
     *
     * Determines if a notice has been dismissed by the current user.
     *
     * @param string $key Notice key
     *
     * @return bool True if dismissed, false otherwise
     * @since  1.0.0
     * @access private
     *
     */
    private function is_dismissed( string $key ): bool {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return false;
        }

        return (bool) get_user_meta( $user_id, $this->get_dismiss_key( $key ), true );
    }

    /**
     * Get dismissal meta key
     *
     * Generates the user meta key for storing dismissal state.
     *
     * @param string $key Notice key
     *
     * @return string Meta key
     * @since  1.0.0
     * @access private
     *
     */
    private function get_dismiss_key( string $key ): string {
        return sprintf( '_dismissed_notice_%s_%s', $this->context, $key );
    }

    /**
     * Check if current page matches notice page restrictions
     *
     * Validates whether the current admin page matches the notice's page restrictions.
     * Supports wildcards and 'all' keyword.
     *
     * @param mixed  $pages        Page restrictions (null, string, or array)
     * @param string $current_page Current admin page
     *
     * @return bool True if page is valid for notice display
     * @since  1.0.0
     * @access private
     *
     */
    private function is_valid_page( $pages, string $current_page ): bool {
        // No restriction
        if ( $pages === null ) {
            return true;
        }

        // Show on all pages
        if ( $pages === 'all' ) {
            return true;
        }

        // Convert to array
        if ( ! is_array( $pages ) ) {
            $pages = [ $pages ];
        }

        foreach ( $pages as $page ) {
            // Exact match
            if ( $page === $current_page ) {
                return true;
            }

            // Wildcard match (e.g., 'sugarcart-*' matches 'sugarcart-settings')
            if ( str_contains( $page, '*' ) ) {
                $pattern = str_replace( '*', '.*', $page );
                if ( preg_match( '/^' . $pattern . '$/', $current_page ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Clear dismissed notices
     *
     * Removes dismissed notice records for this context, allowing notices to be shown again.
     *
     * @param int         $user_id User ID (0 for current user)
     * @param string|null $key     Optional specific notice key to clear (null for all in context)
     *
     * @return int Number of notices cleared
     * @since 1.0.0
     *
     */
    public function clear_dismissed( int $user_id = 0, ?string $key = null ): int {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( ! $user_id ) {
            return 0;
        }

        if ( $key ) {
            // Clear specific notice
            return delete_user_meta( $user_id, $this->get_dismiss_key( $key ) ) ? 1 : 0;
        }

        // Clear all notices for this context
        global $wpdb;

        $pattern   = '_dismissed_notice_' . $this->context . '_%';
        $meta_keys = $wpdb->get_col( $wpdb->prepare(
                "SELECT meta_key FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE %s",
                $user_id,
                $pattern
        ) );

        $count = 0;
        foreach ( $meta_keys as $meta_key ) {
            if ( delete_user_meta( $user_id, $meta_key ) ) {
                $count ++;
            }
        }

        return $count;
    }

}