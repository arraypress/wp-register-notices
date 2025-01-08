# WordPress Admin Notices Registration Library

A comprehensive PHP library for registering and managing WordPress admin notices programmatically. This library provides a robust solution for creating dynamic admin notices with support for dismissible notices, role-based display, conditional rendering, and automatic notice triggering via query parameters.

## Features

- 🚀 Simple notice registration and management
- 🔄 Support for dismissible notices
- 👥 Role-based notice display
- 🎯 Conditional notice rendering
- 📦 Multiple notice types (success, error, warning, info)
- 🔒 Capability checks
- 🛠️ Simple utility functions for quick implementation
- ✅ WP_Error object support
- 🔍 Debug logging support

## Requirements

- PHP 7.4 or higher
- WordPress 5.0 or higher

## Installation

You can install the package via composer:

```bash
composer require arraypress/wp-register-notices
```

## Basic Usage

Here's a simple example of registering admin notices:

```php
// Define your notices
$notices = [
	'settings-updated' => [
		'message'     => 'Settings saved successfully.',
		'class'       => 'updated',
		'dismissible' => true
	],
	'license-expired'  => [
		'message'    => 'Your license has expired.',
		'class'      => 'notice-error',
		'capability' => 'manage_options',
		'conditions' => function () {
			return ! is_license_valid();
		}
	]
];

// Register notices with a prefix
register_admin_notices( $notices, 'my_plugin' );
```

## Configuration Options

Each notice can be configured with:

| Option | Type | Description |
|--------|------|-------------|
| message | string\|callable\|WP_Error | Notice message content (required) |
| class | string | CSS class: 'updated', 'notice-error', 'notice-warning', 'notice-info' |
| is_dismissible | bool | Whether the notice can be dismissed |
| capability | string | Required user capability to view notice |
| conditions | callable | Function returning bool to determine if notice should display |

## Advanced Usage

### Dynamic Messages

Create notices with dynamic content:

```php
$notices = [
	'import-complete' => [
		'message' => function () {
			$count = get_transient( 'import_count' );

			return sprintf(
				'Successfully imported %d items.',
				intval( $count )
			);
		},
		'class'   => 'updated'
	]
];

register_admin_notices( $notices, 'my_plugin' );
```

### Conditional Display

Control notice visibility based on conditions:

```php
$notices = [
	'update-required' => [
		'message'    => 'Plugin update required.',
		'class'      => 'notice-warning',
		'capability' => 'install_plugins',
		'conditions' => function () {
			return version_compare(
				get_plugin_version(),
				'2.0.0',
				'<'
			);
		}
	]
];
```

### Handling WP_Error Objects

Automatically handle error objects:

```php
// In your process function
try {
	process_data();
} catch ( Exception $e ) {
	$error = new WP_Error(
		'process_failed',
		$e->getMessage()
	);
	add_error_notice( $error, 'process-error', [], 'my_plugin' );
}
```

### Full Integration Example

Here's an example showing more advanced usage:

```php
class MyPlugin {
	public function init() {
		// Register all notices
		$this->register_notices();

		// Handle form submissions
		add_action( 'admin_init', [ $this, 'handle_form_submission' ] );
	}

	private function register_notices() {
		$notices = [
			'settings-updated' => [
				'message' => 'Settings saved successfully.',
				'class'   => 'updated'
			],
			'license-status'   => [
				'message'    => function () {
					return $this->get_license_message();
				},
				'class'      => 'notice-warning',
				'capability' => 'manage_options',
				'conditions' => [ $this, 'should_show_license_notice' ]
			]
		];

		register_admin_notices( $notices, 'my_plugin' );
	}

	public function handle_form_submission() {
		if ( ! isset( $_POST['my_plugin_action'] ) ) {
			return;
		}

		check_admin_referer( 'my_plugin_action' );
		$redirect_url = admin_url( 'admin.php?page=my-plugin' );

		if ( $this->process_form() ) {
			$redirect_url = add_query_arg(
				'my-plugin-notice',
				'settings-updated',
				$redirect_url
			);
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}
}
```

## Utility Functions

Global helper functions for easy access:

```php
// Register multiple notices
register_admin_notices( $notices, 'prefix' );

// Add individual notices
add_admin_notice( $message, $id, $type, $args, 'prefix' );
add_success_notice( $message, $id, $args, 'prefix' );
add_error_notice( $message, $id, $args, 'prefix' );
add_warning_notice( $message, $id, $args, 'prefix' );
add_info_notice( $message, $id, $args, 'prefix' );
```

## Debug Mode

Debug logging is enabled when WP_DEBUG is true:

```php
// Logs will include:
// - Notice registration
// - Notice display attempts
// - Capability checks
// - Dismissal actions
// - Error messages
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.

## License

This project is licensed under the GPL2+ License. See the LICENSE file for details.

## Support

For support, please use the [issue tracker](https://github.com/arraypress/wp-register-notices/issues).