<?php
/**
 * Workflow implementation.
 *
 * @package WRDQ
 */

declare(strict_types=1);
namespace WRDQ;

/**
 * Coordinates rendering and delivery state transitions.
 */
final class Workflow {
	/**
	 * Initialize the workflow.
	 *
	 * @param Repository $repository Document persistence.
	 * @param Storage    $storage Storage backend.
	 * @param Mailer     $mailer Mail transport.
	 * @param int        $lease_seconds Lease duration.
	 */
	public function __construct(
		private Repository $repository,
		private Storage $storage,
		private Mailer $mailer,
		private int $lease_seconds = 300
	) {}

	/**
	 * Render or recover a document.
	 *
	 * @param string $key Event identifier.
	 * @param int    $now Current time.
	 * @return string Processing state.
	 * @throws \\InvalidArgumentException For invalid identifiers.
	 * @throws \\RuntimeException When completion fails.
	 */
	public function render( string $key, int $now ): string {
		if ( '' === $key || strlen( $key ) > 191 ) {
			throw new \InvalidArgumentException( 'Invalid event key' );
		}
		$this->repository->ensure( $key );
		if ( 'ready' === $this->repository->get( $key )['status'] ) {
			$this->repository->ensure_delivery( $key );
			return 'already_ready';
		}
		$token = $this->repository->claim( $key, $now, $this->lease_seconds );
		if ( null === $token ) {
			return 'busy';
		}
		// Stable bytes and stable path allow recovery after an interrupted write.
		$contents      = "Fictional document\nEvent: " . $key . "\n";
		[$hash, $path] = $this->storage->put_or_verify( $key, $contents );
		if ( ! $this->repository->complete( $key, $token, $hash, $path ) ) {
			throw new \RuntimeException( 'Lease lost before commit' );
		}
		$this->repository->ensure_delivery( $key );
		return 'ready';
	}

	/**
	 * Perform a single delivery handoff.
	 *
	 * @param string $key Event identifier.
	 * @param int    $now Current time.
	 * @return string Delivery state.
	 * @throws \\Throwable When the transport fails.
	 */
	public function deliver( string $key, int $now ): string {
		$document = $this->repository->get( $key );
		if ( 'ready' !== $document['status'] ) {
			return 'not_ready';
		}
		$this->repository->ensure_delivery( $key );
		if ( ! $this->repository->claim_delivery( $key, $now ) ) {
			return 'already_claimed';
		}
		// The send may succeed and the process may crash before acknowledgment.
		// Never automatically retry an ambiguous handoff.
		try {
			$accepted = $this->mailer->send( $key, $document['path'] );
		} catch ( \Throwable $e ) {
			$this->repository->mark_uncertain( $key );
			throw $e;
		}
		if ( ! $accepted ) {
			$this->repository->mark_uncertain( $key );
			return 'uncertain';
		}
		$this->repository->mark_delivered( $key );
		return 'accepted';
	}
}
