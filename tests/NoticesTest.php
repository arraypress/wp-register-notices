<?php
/**
 * Notice tests.
 *
 * @package ArrayPress\RegisterNotices
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterNotices\Tests;

use ArrayPress\RegisterNotices\Notices;
use PHPUnit\Framework\TestCase;

/**
 * Admin notices, and the two things around them that are not three lines.
 *
 * Showing a message after a redirect, because the action that did the work
 * finished on a different request from the one that reports it. And dismissal
 * that sticks, because core draws the X and hides the notice but does not
 * remember — and a notice that comes back on the next page load is one people
 * learn to ignore.
 */
final class NoticesTest extends TestCase {

	/**
	 * Forget the last test's notices and dismissals.
	 */
	protected function setUp(): void {
		notices_reset_globals();
	}

	/**
	 * Register one notice.
	 *
	 * @param array<string, mixed> $config Its configuration.
	 * @param string               $key    Its key.
	 *
	 * @return bool
	 */
	private function add( array $config = [], string $key = 'saved' ): bool {
		return Notices::add( 'myplugin', $key, array_merge( [ 'message' => 'Settings saved.' ], $config ) );
	}

	/**
	 * Render whatever applies and hand back the markup.
	 *
	 * @return string
	 */
	private function render(): string {
		ob_start();

		try {
			Notices::render( 'myplugin' );
		} finally {
			return (string) ob_get_clean();
		}
	}

	/**
	 * Registering attaches the two hooks a notice needs.
	 */
	public function test_registering_attaches_its_hooks(): void {
		$this->add();

		$this->assertArrayHasKey( 'admin_notices', $GLOBALS['notices_hooks'] );
		$this->assertArrayHasKey( 'wp_ajax_dismiss_notice_myplugin', $GLOBALS['notices_hooks'] );
	}

	/**
	 * A notice with nothing to say is refused.
	 */
	public function test_a_notice_without_a_message_is_refused(): void {
		$this->assertFalse( Notices::add( 'myplugin', 'empty', [] ) );
		$this->assertFalse( Notices::add( 'myplugin', '', [ 'message' => 'Hello' ] ) );
	}

	/**
	 * A triggered notice shows, and an untriggered one does not.
	 *
	 * This is the whole point of the query argument: the message about what
	 * happened belongs to the request after the one that did it.
	 */
	public function test_a_notice_waits_to_be_triggered(): void {
		$this->add();

		$this->assertSame( '', $this->render(), 'It showed without being asked for.' );

		$_GET['notice'] = 'saved';

		$this->assertStringContainsString( 'Settings saved.', $this->render() );
	}

	/**
	 * Another notice's trigger does not show this one.
	 */
	public function test_another_trigger_does_not_show_it(): void {
		$this->add();

		$_GET['notice'] = 'something_else';

		$this->assertSame( '', $this->render() );
	}

	/**
	 * A persistent notice does not wait.
	 *
	 * The other kind: "you have not added an API key yet" is true until it
	 * is not, and nothing triggers it.
	 */
	public function test_a_persistent_notice_shows_unprompted(): void {
		$this->add( [ 'persistent' => true, 'message' => 'Add an API key.' ] );

		$this->assertStringContainsString( 'Add an API key.', $this->render() );
	}

	/**
	 * A condition decides whether a persistent notice still applies.
	 */
	public function test_a_condition_is_honoured(): void {
		$this->add( [ 'persistent' => true, 'condition' => static fn(): bool => false ] );

		$this->assertSame( '', $this->render() );

		notices_reset_globals();
		$this->add( [ 'persistent' => true, 'condition' => static fn(): bool => true ] );

		$this->assertStringContainsString( 'Settings saved.', $this->render() );
	}

	/**
	 * A notice is scoped to the screens it belongs on.
	 */
	public function test_a_notice_can_be_scoped_to_screens(): void {
		$this->add( [ 'persistent' => true, 'screens' => [ 'settings_page_myplugin' ] ] );

		$GLOBALS['notices_screen'] = (object) [ 'id' => 'edit-post' ];
		$this->assertSame( '', $this->render() );

		$GLOBALS['notices_screen'] = (object) [ 'id' => 'settings_page_myplugin' ];
		$this->assertStringContainsString( 'Settings saved.', $this->render() );
	}

	/**
	 * A notice this user may not see is not shown.
	 */
	public function test_a_capability_is_honoured(): void {
		$this->add( [ 'persistent' => true, 'capability' => 'manage_options' ] );

		$GLOBALS['notices_caps'] = [ 'read' ];

		$this->assertSame( '', $this->render() );
	}

	/**
	 * The type reaches the markup, in core's own class.
	 */
	public function test_the_type_is_cores_own_class(): void {
		$this->add( [ 'persistent' => true, 'type' => 'warning' ] );

		$this->assertStringContainsString( 'notice notice-warning', $this->render() );
	}

	/**
	 * A type core does not have becomes one it does.
	 *
	 * Anything else renders as a notice with no colour, which reads as a
	 * notice that failed rather than one of a kind nobody has heard of.
	 */
	public function test_an_unknown_type_falls_back(): void {
		$this->add( [ 'persistent' => true, 'type' => 'catastrophe' ] );

		$this->assertStringContainsString( 'notice-info', $this->render() );
	}

	/**
	 * The type is never guessed from the key.
	 *
	 * It used to be: a key ending in `_error` was an error and everything
	 * else was a success, so `licence_expired` announced itself in green.
	 * That is the sort of magic that is right often enough to be trusted and
	 * wrong exactly when it matters.
	 */
	public function test_the_type_is_not_inferred_from_the_key(): void {
		$this->add( [ 'persistent' => true ], 'licence_expired' );

		$html = $this->render();

		$this->assertStringContainsString( 'notice-info', $html );
		$this->assertStringNotContainsString( 'notice-success', $html );
	}

	/**
	 * A dismissed notice stays dismissed.
	 */
	public function test_a_dismissed_notice_stays_dismissed(): void {
		$this->add( [ 'persistent' => true ] );

		$this->assertStringContainsString( 'Settings saved.', $this->render() );

		$GLOBALS['notices_meta'][1]['_dismissed_notice_myplugin_saved'] = time();

		$this->assertSame( '', $this->render() );
	}

	/**
	 * Dismissal is per user.
	 *
	 * One administrator dismissing a notice must not dismiss it for the
	 * others, who have not read it.
	 */
	public function test_dismissal_is_per_user(): void {
		$this->add( [ 'persistent' => true ] );

		$GLOBALS['notices_meta'][1]['_dismissed_notice_myplugin_saved'] = time();

		$GLOBALS['notices_user'] = 2;

		$this->assertStringContainsString( 'Settings saved.', $this->render() );
	}

	/**
	 * A dismissal can be undone.
	 */
	public function test_a_dismissal_can_be_undone(): void {
		$this->add( [ 'persistent' => true ] );

		$GLOBALS['notices_meta'][1]['_dismissed_notice_myplugin_saved'] = time();

		$this->assertTrue( Notices::undismiss( 'myplugin', 'saved' ) );
		$this->assertStringContainsString( 'Settings saved.', $this->render() );
	}

	/**
	 * A notice that cannot be dismissed is not offered the X.
	 */
	public function test_an_undismissable_notice_has_no_dismiss_button(): void {
		$this->add( [ 'persistent' => true, 'dismissible' => false ] );

		$this->assertStringNotContainsString( 'is-dismissible', $this->render() );
	}

	/**
	 * The script is printed only when something can be dismissed.
	 *
	 * A script on every admin page for a notice that is not there is a
	 * script nobody asked for.
	 */
	public function test_the_script_is_only_printed_when_it_is_needed(): void {
		$this->add( [ 'persistent' => true, 'dismissible' => false ] );

		$this->assertStringNotContainsString( '<script', $this->render() );

		notices_reset_globals();
		$this->add( [ 'persistent' => true ] );

		$this->assertStringContainsString( '<script', $this->render() );
	}

	/**
	 * A message is escaped, and keeps the markup a message may reasonably have.
	 */
	public function test_a_message_is_escaped_but_may_link(): void {
		$this->add( [ 'persistent' => true, 'message' => '<script>alert(1)</script>See <a href="#">settings</a>.' ] );

		$html = $this->render();

		$this->assertStringNotContainsString( '<script>alert', $html );
		$this->assertStringContainsString( '<a href="#">settings</a>', $html );
	}

	/**
	 * The trigger URL is what an action redirects to.
	 */
	public function test_the_trigger_url_carries_the_key(): void {
		$this->assertSame(
			'https://example.test/wp-admin/admin.php?page=x&notice=saved',
			Notices::url( 'saved', 'https://example.test/wp-admin/admin.php?page=x' )
		);
	}
	/**
	 * Dismissing a triggered notice does not hide the next one.
	 *
	 * "Settings saved." is the message about something that just happened.
	 * It used to be remembered like any other dismissal, so one click on
	 * its X and no save ever confirmed itself to that user again.
	 */
	public function test_dismissing_a_triggered_notice_does_not_hide_the_next_one(): void {
		$this->add();

		$GLOBALS['notices_meta'][1]['_dismissed_notice_myplugin_saved'] = time();

		$_GET['notice'] = 'saved';

		$this->assertStringContainsString( 'Settings saved.', $this->render() );
	}

	/**
	 * A triggered notice has an X, but nothing to remember, so no script.
	 */
	public function test_a_triggered_notice_prints_no_dismissal_script(): void {
		$this->add();

		$_GET['notice'] = 'saved';

		$html = $this->render();

		$this->assertStringContainsString( 'is-dismissible', $html );
		$this->assertStringNotContainsString( '<script', $html );
	}

	/**
	 * The trigger is taken back out of the URL once the page has loaded.
	 *
	 * Core strips its own `message` and `updated` from the admin URL after
	 * the load, so a refresh does not announce the save a second time. The
	 * trigger goes on that list.
	 */
	public function test_the_trigger_is_removed_from_the_url_afterwards(): void {
		$this->add();

		$this->assertArrayHasKey( 'removable_query_args', $GLOBALS['notices_hooks'] );
		$this->assertSame( [ 'message', 'notice' ], Notices::removable_query_args( [ 'message' ] ) );
		$this->assertSame( [ 'notice' ], Notices::removable_query_args( [ 'notice' ] ), 'The trigger was added twice.' );
	}
}
