# Register Notices

Declare a plugin's admin notices once, and have them shown when they apply and
stay dismissed when they are dismissed.

## Install

```bash
composer require arraypress/wp-register-notices
```

Requires PHP 8.3.

## Use

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

Two kinds of notice, and the difference is `persistent`.

A **triggered** notice is the message about something that just happened. It
shows on the request that asks for it and not otherwise:

```php
wp_safe_redirect( admin_notice_url( 'settings_saved' ) );
exit;
```

A **persistent** notice is a condition that is true until it is not. Nothing
triggers it; it shows whenever its `condition` says so.

### Options

| Option        | Type     | What it does                                               |
| ------------- | -------- | ---------------------------------------------------------- |
| `message`     | string   | What it says. Required. Run through `wp_kses_post`.         |
| `type`        | string   | `info`, `success`, `warning` or `error`. `info` by default. |
| `persistent`  | bool     | Show without being triggered. `false` by default.           |
| `dismissible` | bool     | Offer the X, and remember it. `true` by default.            |
| `capability`  | string   | Who sees it. `manage_options` by default.                   |
| `screens`     | string[] | Screen ids to limit it to. Everywhere by default.           |
| `condition`   | callable | Returns whether it still applies.                           |

The context — `myplugin` above — is the plugin's own namespace for its
notices. It is what keeps two plugins from sharing a dismissal because they
both called a notice `welcome`.

## What it gets right

Printing a notice is three lines and needs no library. Two things around it
are not.

**Showing one after a redirect.** An action that saves something has to
redirect before it renders, so the message about what happened belongs to a
different request from the work that produced it. That means a query argument,
and reading it back, and only showing the notice the request actually asked
for.

**Dismissal that sticks.** Core draws the X and hides the notice; it does not
remember. A notice that comes back on the next page load is one people learn
to ignore, and then the one that matters is ignored too. Dismissals are stored
per user, so one administrator dismissing a notice does not dismiss it for the
colleagues who have not read it.

The dismiss script is printed only on a request that actually showed a
dismissible notice, rather than enqueued on every admin page against the
chance that one appears.

## Undoing a dismissal

```php
reset_admin_notice( 'myplugin', 'needs_api_key' );
```

Worth doing when the thing the notice was about changes — a licence that
expires again should say so again, even to the person who dismissed it last
year.

`admin_notice_dismissed( 'myplugin', 'needs_api_key' )` answers the question
without changing anything.

## Upgrading from 1.x

**The type is never guessed.** 1.x inferred it from the key: anything not
ending in `_error` was a success, so `licence_expired` announced itself in
green. Set `type` explicitly; it defaults to `info`.

**Auto-dismiss is gone.** A notice that removed itself after a few seconds
took the message with it, sometimes before it had been read.

**The inline stylesheet is gone.** 140 lines of it, styling core components
core already styles — including a class copied in from another library
entirely.

**Dismissal requires a registered key.** The endpoint used to store whatever
key it was handed, so any signed-in user could write unbounded rows of user
meta by dismissing notices that did not exist.

## Testing

```bash
composer test          # phpunit
composer lint          # phpcs, defect sniffs
composer format:check  # phpcs, formatting
```
