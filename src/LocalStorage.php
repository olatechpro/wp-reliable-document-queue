<?php
/**
 * Local file storage implementation for fictional documents.
 *
 * @package WRDQ
 */

declare(strict_types=1);

namespace WRDQ;

/**
 * Writes immutable demo files to a deterministic local path.
 */
final class LocalStorage implements Storage {
	/**
	 * Set the private demo directory.
	 *
	 * @param string $directory Destination directory.
	 */
	public function __construct( private string $directory ) {}

	/**
	 * Publish a file once or verify the existing copy.
	 *
	 * @param string $key Unique event identifier.
	 * @param string $contents Deterministic document contents.
	 * @return array Hash and stored path.
	 * @throws \RuntimeException When writing or verification fails.
	 */
	public function put_or_verify( string $key, string $contents ): array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Local demo storage requires atomic filesystem semantics.
		if ( ! is_dir( $this->directory ) && ! mkdir( $this->directory, 0700, true ) && ! is_dir( $this->directory ) ) {
			throw new \RuntimeException( esc_html__( 'Cannot create directory', 'wp-reliable-document-queue' ) );
		}

		$hash = hash( 'sha256', $contents );
		$path = rtrim( $this->directory, '/' ) . '/' . hash( 'sha256', $key ) . '.txt';
		if ( file_exists( $path ) ) {
			if ( hash_file( 'sha256', $path ) !== $hash ) {
				throw new \RuntimeException( esc_html__( 'Existing document hash mismatch', 'wp-reliable-document-queue' ) );
			}
			return array( $hash, $path );
		}

		$temp = tempnam( $this->directory, 'pending-' );
		if ( false === $temp ) {
			throw new \RuntimeException( esc_html__( 'Cannot create temporary file', 'wp-reliable-document-queue' ) );
		}

		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Atomic local demo file publication.
			if ( strlen( $contents ) !== file_put_contents( $temp, $contents ) ) {
				throw new \RuntimeException( esc_html__( 'Incomplete write', 'wp-reliable-document-queue' ) );
			}
			if ( ! link( $temp, $path ) && ! file_exists( $path ) ) {
				throw new \RuntimeException( esc_html__( 'Cannot publish document', 'wp-reliable-document-queue' ) );
			}
		} finally {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Cleanup of temporary file created by tempnam.
			if ( file_exists( $temp ) && ! unlink( $temp ) ) {
				throw new \RuntimeException( esc_html__( 'Cannot remove temporary file', 'wp-reliable-document-queue' ) );
			}
		}

		if ( hash_file( 'sha256', $path ) !== $hash ) {
			throw new \RuntimeException( esc_html__( 'Published document hash mismatch', 'wp-reliable-document-queue' ) );
		}
		return array( $hash, $path );
	}
}
