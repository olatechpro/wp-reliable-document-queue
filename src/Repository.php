<?php
/**
 * Persistence contract.
 *
 * @package WRDQ
 */

declare(strict_types=1);

namespace WRDQ;

/**
 * Defines document and delivery persistence operations.
 */
interface Repository {
	/**
	 * Create a document record if absent.
	 *
	 * @param string $key key.
	 */
	public function ensure( string $key ): void;
	/**
	 * Retrieve a document record.
	 *
	 * @param string $key key.
	 */
	public function get( string $key ): array;
	/**
	 * Claim processing work.
	 *
	 * @param string $key key.
	 * @param int    $now now.
	 * @param int    $lease_seconds lease seconds.
	 */
	public function claim( string $key, int $now, int $lease_seconds ): ?string;
	/**
	 * Complete the claimed work.
	 *
	 * @param string $key key.
	 * @param string $token token.
	 * @param string $hash hash.
	 * @param string $path path.
	 */
	public function complete( string $key, string $token, string $hash, string $path ): bool;
	/**
	 * Create a delivery record if absent.
	 *
	 * @param string $key key.
	 */
	public function ensure_delivery( string $key ): void;
	/**
	 * Claim an email handoff.
	 *
	 * @param string $key key.
	 * @param int    $now now.
	 */
	public function claim_delivery( string $key, int $now ): bool;
	/**
	 * Record an accepted handoff.
	 *
	 * @param string $key key.
	 */
	public function mark_delivered( string $key ): void;
	/**
	 * Record an uncertain handoff.
	 *
	 * @param string $key key.
	 */
	public function mark_uncertain( string $key ): void;
}
