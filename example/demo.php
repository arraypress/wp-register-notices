<?php
/**
 * Plugin Name: Admin Notices Test
 * Plugin URI: https://github.com/arraypress/wp-register-notices
 * Description: Test plugin for the WordPress Admin Notices Registration System with auto-dismiss functionality
 * Version: 1.1.0
 * Author: David Sherlock
 * Author URI: https://arraypress.com
 * Text Domain: notices-test
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * License: GPL2+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

// Load Composer autoloader (adjust path as needed)
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

/**
 * Class AdminNoticesTestPlugin
 *
 * Test plugin to demonstrate all features of the Admin Notices system.
 */
class AdminNoticesTestPlugin {

    /**
     * Plugin instance
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Get plugin instance
     *
     * @return self
     */
    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action( 'init', [ $this, 'register_notices' ] );
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_post_notices_test_action', [ $this, 'handle_test_actions' ] );
    }

    /**
     * Register all admin notices
     */
    public function register_notices(): void {
        register_admin_notices( 'notices_test', [
            // ========================================
            // STANDARD NOTICES (No Auto-Dismiss)
            // ========================================

            // Simple success messages
                'added'           => __( 'Item added successfully.', 'notices-test' ),
                'updated'         => __( 'Item updated successfully.', 'notices-test' ),
                'deleted'         => __( 'Item deleted successfully.', 'notices-test' ),
                'duplicated'      => __( 'Item duplicated successfully.', 'notices-test' ),

            // ========================================
            // AUTO-DISMISS NOTICES
            // ========================================

            // Quick auto-dismiss (3 seconds, no X button)
                'quick_save'      => [
                        'trigger'      => 'quick_save',
                        'message'      => __( '✨ Settings saved! This message will disappear in 3 seconds.', 'notices-test' ),
                        'type'         => 'success',
                        'auto_dismiss' => 3000,
                        'dismissible'  => false  // No X button since it auto-dismisses quickly
                ],

            // Medium auto-dismiss (5 seconds, with X button)
                'auto_success'    => [
                        'trigger'      => 'auto_success',
                        'message'      => __( '✅ Operation completed! This will auto-dismiss in 5 seconds (or click X).', 'notices-test' ),
                        'type'         => 'success',
                        'auto_dismiss' => 5000,
                        'dismissible'  => true  // Can also manually dismiss
                ],

            // Longer auto-dismiss for info
                'auto_info'       => [
                        'trigger'      => 'auto_info',
                        'message'      => __( '💡 Pro tip: You can hover over auto-dismiss notices to pause them! This disappears in 7 seconds.', 'notices-test' ),
                        'type'         => 'info',
                        'auto_dismiss' => 7000,
                        'dismissible'  => true
                ],

            // Auto-dismiss with dynamic content
                'auto_with_data'  => [
                        'trigger'      => 'auto_saved',
                        'message'      => function ( $args ) {
                            if ( ! empty( $args['id'] ) ) {
                                return sprintf(
                                        __( '✅ Item #%d saved! Auto-dismissing in 4 seconds...', 'notices-test' ),
                                        absint( $args['id'] )
                                );
                            }

                            return __( '✅ Item saved! Auto-dismissing in 4 seconds...', 'notices-test' );
                        },
                        'type'         => 'success',
                        'auto_dismiss' => 4000,
                        'dismissible'  => false,
                        'data'         => [ 'id' ]
                ],

            // ========================================
            // NOTICE TYPES (Standard)
            // ========================================

            // Warning (should NOT auto-dismiss)
                'warning'         => [
                        'trigger'      => 'warning',
                        'message'      => __( '⚠️ This is a warning notice. It will NOT auto-dismiss.', 'notices-test' ),
                        'type'         => 'warning',
                        'auto_dismiss' => 0,  // Explicitly no auto-dismiss
                        'dismissible'  => true
                ],

            // Error (should NEVER auto-dismiss)
                'error'           => [
                        'trigger'      => 'error',
                        'message'      => function ( $args ) {
                            $message = $args['message'] ?? __( '❌ An error occurred. Errors never auto-dismiss.', 'notices-test' );

                            return esc_html( $message );
                        },
                        'type'         => 'error',
                        'auto_dismiss' => 0,  // Never auto-dismiss errors
                        'dismissible'  => true,
                        'data'         => [ 'message' ]
                ],

            // Info (standard, no auto-dismiss)
                'info'            => [
                        'trigger'     => 'info',
                        'message'     => __( 'ℹ️ This is a standard info notice without auto-dismiss.', 'notices-test' ),
                        'type'        => 'info',
                        'dismissible' => true
                ],

            // ========================================
            // DYNAMIC DATA EXAMPLES
            // ========================================

            // Bulk operation with auto-dismiss
                'bulk_processed'  => [
                        'trigger'      => 'bulk_processed',
                        'message'      => function ( $args ) {
                            $count = absint( $args['count'] ?? 0 );
                            if ( $count > 0 ) {
                                return sprintf(
                                        _n(
                                                '✨ %d item processed successfully! (auto-dismiss in 5s)',
                                                '✨ %d items processed successfully! (auto-dismiss in 5s)',
                                                $count,
                                                'notices-test'
                                        ),
                                        $count
                                );
                            }

                            return __( '✨ Items processed successfully!', 'notices-test' );
                        },
                        'type'         => 'success',
                        'auto_dismiss' => 5000,
                        'data'         => [ 'count' ]
                ],

            // ========================================
            // PERSISTENT NOTICES
            // ========================================

            // Persistent with auto-dismiss (unusual but possible)
                'welcome_message' => [
                        'condition'    => function () {
                            return get_option( 'notices_test_show_welcome' ) === 'yes';
                        },
                        'message'      => __( '👋 Welcome! This persistent notice auto-dismisses after 10 seconds.', 'notices-test' ),
                        'type'         => 'info',
                        'persistent'   => true,
                        'auto_dismiss' => 10000,
                        'dismissible'  => true
                ],

            // Persistent warning (no auto-dismiss)
                'config_warning'  => [
                        'condition'    => function () {
                            return get_option( 'notices_test_configured' ) !== 'yes';
                        },
                        'message'      => function () {
                            $url = admin_url( 'admin.php?page=notices-test&tab=settings' );

                            return sprintf(
                                    __( '⚠️ Plugin not configured. <a href="%s">Configure settings</a>. (This does NOT auto-dismiss)', 'notices-test' ),
                                    esc_url( $url )
                            );
                        },
                        'type'         => 'warning',
                        'persistent'   => true,
                        'dismissible'  => false,
                        'auto_dismiss' => 0  // Important notices don't auto-dismiss
                ],

            // ========================================
            // MIXED SCENARIOS
            // ========================================

            // Success with optional auto-dismiss based on type
                'file_uploaded'   => [
                        'trigger'      => 'file_uploaded',
                        'message'      => function ( $args ) {
                            $filename = $args['file'] ?? 'file';
                            $size     = $args['size'] ?? 0;

                            if ( $size > 1048576 ) { // > 1MB
                                return sprintf(
                                        __( '📁 Large file "%s" uploaded (%s). This will stay visible.', 'notices-test' ),
                                        esc_html( $filename ),
                                        size_format( $size )
                                );
                            } else {
                                return sprintf(
                                        __( '📁 File "%s" uploaded successfully! (auto-dismiss in 4s)', 'notices-test' ),
                                        esc_html( $filename )
                                );
                            }
                        },
                        'type'         => 'success',
                        'auto_dismiss' => function ( $args ) {
                            // Only auto-dismiss small files
                            $size = $args['size'] ?? 0;

                            return $size <= 1048576 ? 4000 : 0;
                        },
                        'data'         => [ 'file', 'size' ]
                ],
        ], [
                'pages'      => [ 'notices-test', 'notices-test-*' ],
                'capability' => 'manage_options'
        ] );
    }

    /**
     * Add admin menu page
     */
    public function add_admin_menu(): void {
        add_menu_page(
                __( 'Notices Test', 'notices-test' ),
                __( 'Notices Test', 'notices-test' ),
                'manage_options',
                'notices-test',
                [ $this, 'render_admin_page' ],
                'dashicons-megaphone',
                99
        );
    }

    /**
     * Render admin page
     */
    public function render_admin_page(): void {
        // Handle persistent notice toggles
        if ( isset( $_GET['show_welcome'] ) ) {
            update_option( 'notices_test_show_welcome', $_GET['show_welcome'] );
            wp_redirect( remove_query_arg( 'show_welcome' ) );
            exit;
        }

        if ( isset( $_GET['configure'] ) ) {
            update_option( 'notices_test_configured', $_GET['configure'] );
            wp_redirect( remove_query_arg( 'configure' ) );
            exit;
        }

        $show_welcome = get_option( 'notices_test_show_welcome' ) === 'yes';
        $configured   = get_option( 'notices_test_configured' ) === 'yes';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Admin Notices Test - With Auto-Dismiss', 'notices-test' ); ?></h1>

            <!-- Auto-Dismiss Examples -->
            <div class="card" style="border-left: 4px solid #00a32a;">
                <h2>🚀 <?php esc_html_e( 'Auto-Dismiss Notices', 'notices-test' ); ?></h2>
                <p><?php esc_html_e( 'These notices automatically disappear after a set time. Hover to pause them!', 'notices-test' ); ?></p>

                <p>
                    <a href="<?php echo esc_url( add_query_arg( 'quick_save', '1' ) ); ?>"
                       class="button button-primary">
                        ⚡ <?php esc_html_e( 'Quick Save (3s)', 'notices-test' ); ?>
                    </a>
                    <a href="<?php echo esc_url( add_query_arg( 'auto_success', '1' ) ); ?>" class="button">
                        ✅ <?php esc_html_e( 'Auto Success (5s)', 'notices-test' ); ?>
                    </a>
                    <a href="<?php echo esc_url( add_query_arg( 'auto_info', '1' ) ); ?>" class="button">
                        💡 <?php esc_html_e( 'Auto Info (7s)', 'notices-test' ); ?>
                    </a>
                    <a href="<?php echo esc_url( add_query_arg( [ 'auto_saved' => '1', 'id' => '999' ] ) ); ?>"
                       class="button">
                        💾 <?php esc_html_e( 'With Data (4s)', 'notices-test' ); ?>
                    </a>
                </p>
            </div>

            <!-- Standard Notices (No Auto-Dismiss) -->
            <div class="card">
                <h2>📌 <?php esc_html_e( 'Standard Notices (No Auto-Dismiss)', 'notices-test' ); ?></h2>
                <p><?php esc_html_e( 'Traditional notices that stay until dismissed:', 'notices-test' ); ?></p>

                <p>
                    <a href="<?php echo esc_url( add_query_arg( 'added', '1' ) ); ?>" class="button">
                        ➕ <?php esc_html_e( 'Added', 'notices-test' ); ?>
                    </a>
                    <a href="<?php echo esc_url( add_query_arg( 'updated', '1' ) ); ?>" class="button">
                        ✏️ <?php esc_html_e( 'Updated', 'notices-test' ); ?>
                    </a>
                    <a href="<?php echo esc_url( add_query_arg( 'deleted', '1' ) ); ?>" class="button">
                        🗑️ <?php esc_html_e( 'Deleted', 'notices-test' ); ?>
                    </a>
                    <a href="<?php echo esc_url( add_query_arg( 'duplicated', '1' ) ); ?>" class="button">
                        📋 <?php esc_html_e( 'Duplicated', 'notices-test' ); ?>
                    </a>
                </p>
            </div>

            <!-- Important Notices (Never Auto-Dismiss) -->
            <div class="card" style="border-left: 4px solid #d63638;">
                <h2>⚠️ <?php esc_html_e( 'Important Notices (Never Auto-Dismiss)', 'notices-test' ); ?></h2>
                <p><?php esc_html_e( 'Warnings and errors should never auto-dismiss:', 'notices-test' ); ?></p>

                <p>
                    <a href="<?php echo esc_url( add_query_arg( 'warning', '1' ) ); ?>" class="button">
                        ⚠️ <?php esc_html_e( 'Warning', 'notices-test' ); ?>
                    </a>
                    <a href="<?php echo esc_url( add_query_arg( [
                            'error'   => '1',
                            'message' => 'Critical error occurred!'
                    ] ) ); ?>" class="button">
                        ❌ <?php esc_html_e( 'Error', 'notices-test' ); ?>
                    </a>
                    <a href="<?php echo esc_url( add_query_arg( 'info', '1' ) ); ?>" class="button">
                        ℹ️ <?php esc_html_e( 'Info (Standard)', 'notices-test' ); ?>
                    </a>
                </p>
            </div>

            <!-- Bulk Operations with Auto-Dismiss -->
            <div class="card" style="border-left: 4px solid #72aee6;">
                <h2>📦 <?php esc_html_e( 'Bulk Operations', 'notices-test' ); ?></h2>
                <p><?php esc_html_e( 'Bulk operations with counts that auto-dismiss:', 'notices-test' ); ?></p>

                <p>
                    <a href="<?php echo esc_url( add_query_arg( [ 'bulk_processed' => '1', 'count' => '3' ] ) ); ?>"
                       class="button">
                        3️⃣ <?php esc_html_e( 'Process 3 Items', 'notices-test' ); ?>
                    </a>
                    <a href="<?php echo esc_url( add_query_arg( [ 'bulk_processed' => '1', 'count' => '10' ] ) ); ?>"
                       class="button">
                        🔟 <?php esc_html_e( 'Process 10 Items', 'notices-test' ); ?>
                    </a>
                    <a href="<?php echo esc_url( add_query_arg( [ 'bulk_processed' => '1', 'count' => '100' ] ) ); ?>"
                       class="button">
                        💯 <?php esc_html_e( 'Process 100 Items', 'notices-test' ); ?>
                    </a>
                </p>
            </div>

            <!-- Persistent Notices -->
            <div class="card" style="border-left: 4px solid #dba617;">
                <h2>🔄 <?php esc_html_e( 'Persistent Notices', 'notices-test' ); ?></h2>
                <p><?php esc_html_e( 'Notices that appear based on conditions:', 'notices-test' ); ?></p>

                <h3><?php esc_html_e( 'Welcome Message (with auto-dismiss):', 'notices-test' ); ?></h3>
                <p>
                    <?php if ( $show_welcome ) : ?>
                        <a href="<?php echo esc_url( add_query_arg( 'show_welcome', 'no' ) ); ?>"
                           class="button button-secondary">
                            🔕 <?php esc_html_e( 'Hide Welcome', 'notices-test' ); ?>
                        </a>
                        <span class="description"><?php esc_html_e( '✅ Currently showing (10s auto-dismiss)', 'notices-test' ); ?></span>
                    <?php else : ?>
                        <a href="<?php echo esc_url( add_query_arg( 'show_welcome', 'yes' ) ); ?>"
                           class="button button-primary">
                            👋 <?php esc_html_e( 'Show Welcome', 'notices-test' ); ?>
                        </a>
                        <span class="description"><?php esc_html_e( '❌ Currently hidden', 'notices-test' ); ?></span>
                    <?php endif; ?>
                </p>

                <h3><?php esc_html_e( 'Configuration Warning (no auto-dismiss):', 'notices-test' ); ?></h3>
                <p>
                    <?php if ( $configured ) : ?>
                        <a href="<?php echo esc_url( add_query_arg( 'configure', 'no' ) ); ?>"
                           class="button button-secondary">
                            ⚠️ <?php esc_html_e( 'Mark Not Configured', 'notices-test' ); ?>
                        </a>
                        <span class="description"><?php esc_html_e( '✅ Currently configured', 'notices-test' ); ?></span>
                    <?php else : ?>
                        <a href="<?php echo esc_url( add_query_arg( 'configure', 'yes' ) ); ?>"
                           class="button button-primary">
                            ✅ <?php esc_html_e( 'Mark Configured', 'notices-test' ); ?>
                        </a>
                        <span class="description"><?php esc_html_e( '⚠️ Warning showing', 'notices-test' ); ?></span>
                    <?php endif; ?>
                </p>
            </div>

            <!-- Form Test -->
            <div class="card">
                <h2>📝 <?php esc_html_e( 'Form Submission Test', 'notices-test' ); ?></h2>
                <p><?php esc_html_e( 'Test form submission with redirect and auto-dismiss:', 'notices-test' ); ?></p>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'notices_test_form', '_wpnonce' ); ?>
                    <input type="hidden" name="action" value="notices_test_action">

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="auto_dismiss"><?php esc_html_e( 'Auto-Dismiss?', 'notices-test' ); ?></label>
                            </th>
                            <td>
                                <select id="auto_dismiss" name="auto_dismiss">
                                    <option value="0"><?php esc_html_e( 'No auto-dismiss', 'notices-test' ); ?></option>
                                    <option value="3000"><?php esc_html_e( '3 seconds', 'notices-test' ); ?></option>
                                    <option value="5000"><?php esc_html_e( '5 seconds', 'notices-test' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="trigger_error"><?php esc_html_e( 'Trigger Error?', 'notices-test' ); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" id="trigger_error" name="trigger_error" value="1"/>
                                <label for="trigger_error"><?php esc_html_e( 'Errors never auto-dismiss', 'notices-test' ); ?></label>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary">
                            📤 <?php esc_html_e( 'Submit Form', 'notices-test' ); ?>
                        </button>
                    </p>
                </form>
            </div>

            <!-- Clear/Reset -->
            <div class="card">
                <h2>🔄 <?php esc_html_e( 'Clear / Reset', 'notices-test' ); ?></h2>
                <p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=notices-test' ) ); ?>"
                       class="button button-secondary">
                        🔄 <?php esc_html_e( 'Clear All Notices', 'notices-test' ); ?>
                    </a>
                </p>
            </div>

            <!-- Instructions -->
            <div class="card" style="background: #f0f0f1;">
                <h2>📖 <?php esc_html_e( 'Instructions', 'notices-test' ); ?></h2>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><?php esc_html_e( '🎯 Auto-dismiss notices have a progress bar at the bottom', 'notices-test' ); ?></li>
                    <li><?php esc_html_e( '🖱️ Hover over auto-dismiss notices to pause them', 'notices-test' ); ?></li>
                    <li><?php esc_html_e( '✅ Success notices can auto-dismiss (optional)', 'notices-test' ); ?></li>
                    <li><?php esc_html_e( 'ℹ️ Info notices can auto-dismiss (optional)', 'notices-test' ); ?></li>
                    <li><?php esc_html_e( '⚠️ Warning notices should NOT auto-dismiss', 'notices-test' ); ?></li>
                    <li><?php esc_html_e( '❌ Error notices should NEVER auto-dismiss', 'notices-test' ); ?></li>
                    <li><?php esc_html_e( '🔄 Persistent notices can optionally auto-dismiss', 'notices-test' ); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Handle test form submissions
     */
    public function handle_test_actions(): void {
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'notices_test_form' ) ) {
            wp_die( 'Security check failed' );
        }

        $redirect_args = [];
        $auto_dismiss  = absint( $_POST['auto_dismiss'] ?? 0 );

        if ( ! empty( $_POST['trigger_error'] ) ) {
            // Errors never auto-dismiss regardless of setting
            $redirect_args['error']   = '1';
            $redirect_args['message'] = 'Form submission error! This will NOT auto-dismiss.';
        } else {
            // Use the selected auto-dismiss option
            if ( $auto_dismiss > 0 ) {
                $redirect_args['quick_save'] = '1';
            } else {
                $redirect_args['updated'] = '1';
            }
        }

        $redirect_url = add_query_arg(
                $redirect_args,
                admin_url( 'admin.php?page=notices-test' )
        );

        wp_redirect( $redirect_url );
        exit;
    }
}

// Initialize the test plugin
AdminNoticesTestPlugin::instance();

// Set default options
add_action( 'admin_init', function () {
    if ( get_option( 'notices_test_show_welcome' ) === false ) {
        update_option( 'notices_test_show_welcome', 'no' );
    }
    if ( get_option( 'notices_test_configured' ) === false ) {
        update_option( 'notices_test_configured', 'no' );
    }
} );