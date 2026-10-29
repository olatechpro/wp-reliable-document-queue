<?php
/**
 * Mail transport contract.
 *
 * @package WRDQ
 */

declare(strict_types=1);

namespace WRDQ;

/**
 * Defines transport acceptance for a fictional delivery.
 */
interface Mailer {
	/**
	 * Simulate a transport handoff.
	 *
	 * @param string $key key.
	 * @param string $path path.
	 */
	public function send( string $key, string $path ): bool;
}
