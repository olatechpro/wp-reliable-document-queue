<?php
/**
 * Plugin Name: WP Reliable Document Queue Demo
 * Plugin URI: https://github.com/olatechpro/wp-reliable-document-queue
 * Description: Demonstrates reliable document processing with Action Scheduler.
 * Version: 0.1.0
 * Requires at least: 6.2
 * Requires PHP: 8.1
 * Text Domain: wp-reliable-document-queue
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 *
 * @package WRDQ
 */

defined( 'ABSPATH' ) || exit;

$wrdq_autoload  = __DIR__ . '/vendor/autoload.php';
$wrdq_scheduler = __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';

if ( ! is_readable( $wrdq_autoload ) ) {
	return;
}
require_once $wrdq_autoload;

// Register the bundled version before plugins_loaded. Action Scheduler
// selects the newest registered version when other plugins bundle it.
if ( file_exists( $wrdq_scheduler ) ) {
	require_once $wrdq_scheduler;
}


register_activation_hook( __FILE__, array( \WRDQ\Installer::class, 'activate' ) );

add_action(
	'init',
	static function (): void {
		load_plugin_textdomain(
			'wp-reliable-document-queue',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	},
	0
);

add_action(
	'plugins_loaded',
	static function (): void {
		\WRDQ\Plugin::register();
	}
);

/**
 * Enqueue a fictional document processing event.
 *
 * @param string $key Unique event identifier.
 * @return void
 */
function wrdq_enqueue_document( string $key ): void {
	\WRDQ\Plugin::enqueue( $key );
}
