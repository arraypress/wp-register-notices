# Register Notices

Declare a plugin's admin notices, including the ones that have to survive a
redirect.

## What it does

An admin notice is easy until it needs to appear after a save. Then it is a
transient, or a query argument, or a flag in user meta — and a dismissal that
has to be remembered per user, and a condition that decides whether it is
shown at all.

This is the declaration. You describe the notices once; showing, dismissing
and remembering are handled.

## Features

* Declare a notice with its message, type and when it should appear
* Show one after a redirect, without inventing a transient for it
* Let a user dismiss a notice and have it stay dismissed, for that user
* Show a notice only while a condition holds — no API key set, say
* Bring a dismissed notice back, when whatever it warned about returns
* Keep notices out of screens they have nothing to do with

## Installation

```bash
composer require arraypress/wp-register-notices
```

## Quick start

```php
add_action( 'admin_init', function () {
	register_admin_notices( 'myplugin', [
		'settings_saved' => [
			'message' => __( 'Settings saved.', 'my-plugin' ),
			'type'    => 'success',
		],
		'needs_api_key'  => [
			'message'    => __( 'Add an API key to start syncing.', 'my-plugin' ),
			'type'       => 'warning',
			'persistent' => true,
			'condition'  => fn(): bool => ! get_option( 'myplugin_api_key' ),
		],
	] );
} );
```

Showing one after a save is the part that is usually fiddly:

```php
wp_safe_redirect( admin_notice_url( 'settings_saved' ) );
exit;
```

## Requirements

* PHP 8.3 or later
* WordPress 7.1 or later

## License

GPL-2.0-or-later
