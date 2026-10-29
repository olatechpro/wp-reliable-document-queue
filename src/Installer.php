<?php
/**
 * Database installer for the demonstration plugin.
 *
 * @package WRDQ
 */

declare(strict_types=1);

namespace WRDQ;

/**
 * Installer implementation.
 */
final class Installer {
	/**
	 * Create database tables.
	 */
	public static function activate(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$collate = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$wpdb->prefix}wrdq_documents (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				event_key varchar(191) NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'pending',
				content_hash char(64) DEFAULT NULL,
				file_path text DEFAULT NULL,
				claim_token varchar(64) DEFAULT NULL,
				lease_until bigint(20) DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY event_key (event_key)
			) $collate;"
		);
		dbDelta(
			"CREATE TABLE {$wpdb->prefix}wrdq_deliveries (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				event_key varchar(191) NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'pending',
				started_at bigint(20) DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY event_key (event_key)
			) $collate;"
		);
	}
}
