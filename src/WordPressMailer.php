<?php
/**
 * WordPressMailer implementation.
 *
 * @package WRDQ
 */

declare(strict_types=1);
namespace WRDQ;

/**
 * WordPress integration service.
 */
final class WordPressMailer implements Mailer {
	/**
	 * Simulate a transport handoff.
	 *
	 * @param string $key key.
	 * @param string $path path.
	 */
	public function send( string $key, string $path ): bool {
		// Explicitly disabled: this portfolio example must not send real mail.
		return true;
	}
}
