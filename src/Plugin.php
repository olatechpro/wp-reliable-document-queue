<?php
/**
 * WordPress integration for the queue demonstration.
 *
 * @package WRDQ
 */

declare(strict_types=1);

namespace WRDQ;

use RuntimeException;

/**
 * Plugin implementation.
 */
final class Plugin {
	private const GROUP = 'wrdq';

	/**
	 * Register WordPress hooks.
	 */
	public static function register(): void {
		add_action( 'admin_notices', array( self::class, 'dependency_notice' ) );
		add_action( 'wrdq_render', array( self::class, 'render' ), 10, 1 );
		add_action( 'wrdq_deliver', array( self::class, 'deliver' ), 10, 1 );
	}

	/**
	 * Display the Action Scheduler dependency notice.
	 */
	public static function dependency_notice(): void {
		if ( function_exists( 'as_enqueue_async_action' ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'WP Reliable Document Queue requires Action Scheduler. Activate WooCommerce or another plugin that provides it.', 'wp-reliable-document-queue' )
		);
	}

	/**
	 * Build the workflow service.
	 *
	 * @throws \RuntimeException If the upload directory is unavailable.
	 */
	private static function workflow(): Workflow {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			throw new RuntimeException( esc_html__( 'WordPress upload path unavailable', 'wp-reliable-document-queue' ) );
		}

		$directory = dirname( $uploads['basedir'] ) . '/wrdq-private-demo';

		return new Workflow(
			new WordPressRepository(),
			new LocalStorage( $directory ),
			new WordPressMailer()
		);
	}

	/**
	 * Render a document.
	 *
	 * @param string $key key.
	 */
	public static function render( string $key ): void {
		$result = self::workflow()->render( $key, time() );

		if ( 'ready' === $result || 'already_ready' === $result ) {
			if ( function_exists( 'as_enqueue_async_action' ) ) {
				as_enqueue_async_action( 'wrdq_deliver', array( $key ), self::GROUP, true );
			}
		} elseif ( 'busy' === $result && function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time() + 360, 'wrdq_render', array( $key ), self::GROUP, true );
		}
	}

	/**
	 * Process a delivery.
	 *
	 * @param string $key key.
	 */
	public static function deliver( string $key ): void {
		self::workflow()->deliver( $key, time() );
	}

	/**
	 * Queue a document event.
	 *
	 * @param string $key key.
	 * @throws \RuntimeException When Action Scheduler is unavailable.
	 */
	public static function enqueue( string $key ): void {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			throw new RuntimeException( esc_html__( 'Action Scheduler is required', 'wp-reliable-document-queue' ) );
		}

		as_enqueue_async_action( 'wrdq_render', array( $key ), self::GROUP, true );

		// A separate watchdog retries processing after a crashed worker's lease expires.
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time() + 420, 'wrdq_render', array( $key ), self::GROUP, true );
		}
	}
}
