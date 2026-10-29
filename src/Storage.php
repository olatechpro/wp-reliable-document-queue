<?php
/**
 * Storage contract.
 *
 * @package WRDQ
 */

declare(strict_types=1);

namespace WRDQ;

/**
 * Defines durable storage operations.
 */
interface Storage {
	/**
	 * Write or verify stored bytes.
	 *
	 * @param string $key key.
	 * @param string $contents contents.
	 */
	public function put_or_verify( string $key, string $contents ): array;
}
