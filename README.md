# WordPress Admin Notices Registration System

A declarative, lightweight system for managing WordPress admin notices with automatic GET parameter handling, conditional display, and dismissible notices.

## Features

* **Declarative API**: Register all your notices in one place with a clean configuration array
* **Automatic Triggers**: Notices appear based on GET parameters (e.g., `?updated=1`)
* **Dynamic Messages**: Support for callbacks with data interpolation
* **Conditional Display**: Show notices based on conditions (e.g., missing API keys)
* **Dismissible Notices**: Built-in AJAX dismissal with per-user persistence
* **Page Targeting**: Limit notices to specific admin pages with wildcard support
* **Smart Type Detection**: Automatically infers notice type from key naming
* **Capability Checks**: Restrict notices based on user permissions

## Installation

Install via Composer:
```bash
composer require arraypress/wp-register-notices
```

## Basic Usage
```php
use function ArrayPress\AdminNotices\register_admin_notices;

// Register your notices during plugin initialization
register_admin_notices( 'myplugin', [
    // Simple success messages
    'added'   => __( 'Item added successfully.', 'myplugin' ),
    'updated' => __( 'Item updated successfully.', 'myplugin' ),
    'deleted' => __( 'Item deleted successfully.', 'myplugin' ),
] );

// Trigger by redirecting with the parameter
wp_redirect( admin_url( 'admin.php?page=myplugin&updated=1' ) );
```

## Advanced Examples

### Dynamic Messages with Data
```php
register_admin_notices( 'myplugin', [
    'bulk_deleted' => [
        'message' => function( $args ) {
            $count = absint( $args['count'] ?? 0 );
            return sprintf(
                _n( '%d item deleted.', '%d items deleted.', $count, 'myplugin' ),
                $count
            );
        },
        'type' => 'success',
        'data' => ['count']  // Collect these GET parameters
    ]
] );

// Trigger: ?bulk_deleted=1&count=5
// Shows: "5 items deleted."
```

### Persistent Conditional Notices
```php
register_admin_notices( 'myplugin', [
    'api_key_missing' => [
        'condition' => function() {
            return empty( get_option( 'myplugin_api_key' ) );
        },
        'message' => function() {
            $url = admin_url( 'admin.php?page=myplugin-settings' );
            return sprintf( 
                __( 'API key required. <a href="%s">Configure settings</a>.', 'myplugin' ),
                esc_url( $url )
            );
        },
        'type'        => 'warning',
        'persistent'  => true,        // Shows on every page load
        'dismissible' => false         // Can't be dismissed
    ]
] );
```

### Page-Specific Notices
```php
register_admin_notices( 'myplugin', [
    'settings_saved' => __( 'Settings saved successfully.', 'myplugin' ),
    'license_activated' => __( 'License activated.', 'myplugin' ),
], [
    'pages' => ['myplugin-settings', 'myplugin-license'],  // Only these pages
    'capability' => 'manage_options'                        // Admin only
] );
```

### Error Handling
```php
register_admin_notices( 'myplugin', [
    'error' => [
        'message' => function( $args ) {
            return esc_html( $args['message'] ?? __( 'An error occurred.', 'myplugin' ) );
        },
        'type' => 'error',
        'data' => ['message']
    ]
] );

// Trigger: ?error=1&message=Invalid+email+address
// Shows: "Invalid email address"
```

### Working with IDs
```php
register_admin_notices( 'myplugin', [
    'item_updated' => [
        'message' => function( $args ) {
            if ( ! empty( $args['id'] ) ) {
                return sprintf( __( 'Item #%d updated.', 'myplugin' ), $args['id'] );
            }
            return __( 'Item updated.', 'myplugin' );
        },
        'data' => ['id']
    ]
] );

// Trigger: ?item_updated=1&id=123
// Shows: "Item #123 updated."
```

## Configuration Options

### Notice Configuration

| Option | Type | Description | Default |
|--------|------|-------------|---------|
| `message` | string\|callable | The notice message or callback | Required |
| `type` | string | Notice type: `success`, `error`, `warning`, `info` | `success` |
| `trigger` | string | GET parameter that triggers the notice | Key name |
| `data` | array | GET parameters to pass to message callback | `[]` |
| `condition` | callable | Function to determine if notice should show | `null` |
| `persistent` | bool | Shows on every page load when condition is met | `false` |
| `dismissible` | bool | Whether notice can be dismissed | `true` |
| `capability` | string | Required capability to see notice | `null` |
| `pages` | array\|string | Admin pages where notice appears (`'all'` for global) | `null` |

### Global Options
```php
register_admin_notices( 'myplugin', $notices, [
    'pages'       => ['myplugin', 'myplugin-*'],  // Wildcard support
    'capability'  => 'manage_options',
    'dismissible' => true                          // Default for all notices
] );
```

## Type Detection

The system automatically infers notice types from key names:
```php
register_admin_notices( 'myplugin', [
    'item_updated'        => 'Updated!',    // success (default)
    'item_deleted_error'  => 'Failed!',     // error (suffix)
    'warning_low_stock'   => 'Low stock',   // warning (prefix)
    'info_new_feature'    => 'New feature'  // info (prefix)
] );
```

## Real-World Example
```php
// In your plugin's main file or admin initialization
add_action( 'admin_init', function() {
    register_admin_notices( 'woocommerce_extras', [
        // Simple CRUD notifications
        'product_added'   => __( 'Product added successfully.', 'wc-extras' ),
        'product_updated' => __( 'Product updated successfully.', 'wc-extras' ),
        'product_deleted' => __( 'Product deleted successfully.', 'wc-extras' ),
        
        // Bulk operations with counts
        'bulk_updated' => [
            'message' => function( $args ) {
                $count = absint( $args['count'] ?? 0 );
                return sprintf( 
                    _n( 
                        '%d product updated.', 
                        '%d products updated.', 
                        $count, 
                        'wc-extras' 
                    ), 
                    $count 
                );
            },
            'data' => ['count']
        ],
        
        // Persistent warnings
        'shipping_not_configured' => [
            'condition' => function() {
                $zones = WC_Shipping_Zones::get_zones();
                return empty( $zones );
            },
            'message' => __( 'No shipping zones configured. Products cannot be shipped.', 'wc-extras' ),
            'type' => 'warning',
            'persistent' => true
        ],
        
        // Error handling
        'import_failed' => [
            'message' => function( $args ) {
                $error = $args['error'] ?? __( 'Unknown error', 'wc-extras' );
                $line = $args['line'] ?? 0;
                
                if ( $line > 0 ) {
                    return sprintf( 
                        __( 'Import failed at line %d: %s', 'wc-extras' ), 
                        $line, 
                        esc_html( $error ) 
                    );
                }
                
                return sprintf( __( 'Import failed: %s', 'wc-extras' ), esc_html( $error ) );
            },
            'type' => 'error',
            'data' => ['error', 'line']
        ]
    ], [
        'pages' => ['woocommerce', 'edit-product', 'product'],
        'capability' => 'manage_woocommerce'
    ] );
} );

// In your form handler
function handle_product_save() {
    $product_id = save_product( $_POST );
    
    if ( is_wp_error( $product_id ) ) {
        $url = add_query_arg( [
            'import_failed' => 1,
            'error' => $product_id->get_error_message()
        ], admin_url( 'edit.php?post_type=product' ) );
    } else {
        $url = add_query_arg( [
            'product_updated' => 1,
            'id' => $product_id
        ], admin_url( 'edit.php?post_type=product' ) );
    }
    
    wp_redirect( $url );
    exit;
}
```

## Requirements

- PHP 7.4 or later
- WordPress 5.0 or later

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the GPL-2.0-or-later License.

## Credits

Created by [David Sherlock](https://davidsherlock.com) at [ArrayPress](https://arraypress.com).