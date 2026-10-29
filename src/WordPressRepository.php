<?php
/**
 * WordPress database repository.
 *
 * @package WRDQ
 */

declare(strict_types=1);

namespace WRDQ;

/**
 * Persists document and delivery state using atomic SQL updates.
 */
final class WordPressRepository implements Repository {
	/**
	 * Document table name.
	 *
	 * @var string
	 */
	private string $documents;

	/**
	 * Delivery table name.
	 *
	 * @var string
	 */
	private string $deliveries;

	/**
	 * Initialize table names.
	 */
	public function __construct() {
		global $wpdb;
		$this->documents  = $wpdb->prefix . 'wrdq_documents';
		$this->deliveries = $wpdb->prefix . 'wrdq_deliveries';
	}

	/**
	 * Create a document record once.
	 *
	 * @param string $key Unique event identifier.
	 */
	public function ensure( string $key ): void {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO %i (event_key, status) VALUES (%s, %s)',
				$this->documents,
				$key,
				'pending'
			)
		);
	}

	/**
	 * Read the current document state.
	 *
	 * @param string $key Unique event identifier.
	 * @return array Document state.
	 * @throws \RuntimeException If the document is missing.
	 */
	public function get( string $key ): array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT status, file_path, content_hash FROM %i WHERE event_key = %s',
				$this->documents,
				$key
			),
			ARRAY_A
		);
		if ( ! $row ) {
			throw new \RuntimeException( esc_html__( 'Unknown document', 'wp-reliable-document-queue' ) );
		}
		return array(
			'status' => $row['status'],
			'path'   => $row['file_path'],
			'hash'   => $row['content_hash'],
		);
	}

	/**
	 * Atomically claim pending or expired work.
	 *
	 * @param string $key Unique event identifier.
	 * @param int    $now Current timestamp.
	 * @param int    $lease_seconds Claim duration.
	 * @return string|null Claim token, or null if unavailable.
	 * @throws \Exception If token generation fails.
	 */
	public function claim( string $key, int $now, int $lease_seconds ): ?string {
		global $wpdb;
		$token   = bin2hex( random_bytes( 16 ) );
		$changed = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET status = %s, claim_token = %s, lease_until = %d
				WHERE event_key = %s AND (status = %s OR (status = %s AND lease_until < %d))',
				$this->documents,
				'processing',
				$token,
				$now + $lease_seconds,
				$key,
				'pending',
				'processing',
				$now
			)
		);
		return 1 === $changed ? $token : null;
	}

	/**
	 * Complete a document only for the current claim holder.
	 *
	 * @param string $key Unique event identifier.
	 * @param string $token Current claim token.
	 * @param string $hash Verified SHA256 hash.
	 * @param string $path Stored file path.
	 * @return bool Whether the claim was committed.
	 */
	public function complete( string $key, string $token, string $hash, string $path ): bool {
		global $wpdb;
		$changed = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET status = %s, content_hash = %s, file_path = %s,
				claim_token = NULL, lease_until = NULL
				WHERE event_key = %s AND status = %s AND claim_token = %s',
				$this->documents,
				'ready',
				$hash,
				$path,
				$key,
				'processing',
				$token
			)
		);
		return 1 === $changed;
	}

	/**
	 * Create the delivery row once.
	 *
	 * @param string $key Unique event identifier.
	 */
	public function ensure_delivery( string $key ): void {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO %i (event_key, status) VALUES (%s, %s)',
				$this->deliveries,
				$key,
				'pending'
			)
		);
	}

	/**
	 * Claim a pending delivery only once.
	 *
	 * @param string $key Unique event identifier.
	 * @param int    $now Current timestamp.
	 * @return bool Whether delivery was claimed.
	 */
	public function claim_delivery( string $key, int $now ): bool {
		global $wpdb;
		// A sending record is never reclaimed because the handoff may have succeeded.
		return 1 === $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET status = %s, started_at = %d WHERE event_key = %s AND status = %s',
				$this->deliveries,
				'sending',
				$now,
				$key,
				'pending'
			)
		);
	}

	/**
	 * Mark an accepted transport handoff.
	 *
	 * @param string $key Unique event identifier.
	 */
	public function mark_delivered( string $key ): void {
		global $wpdb;
		$wpdb->update(
			$this->deliveries,
			array( 'status' => 'accepted' ),
			array(
				'event_key' => $key,
				'status'    => 'sending',
			)
		);
	}

	/**
	 * Mark an ambiguous transport handoff.
	 *
	 * @param string $key Unique event identifier.
	 */
	public function mark_uncertain( string $key ): void {
		global $wpdb;
		$wpdb->update(
			$this->deliveries,
			array( 'status' => 'uncertain' ),
			array(
				'event_key' => $key,
				'status'    => 'sending',
			)
		);
	}
}
