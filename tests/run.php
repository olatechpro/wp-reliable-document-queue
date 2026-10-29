<?php

/**
 * Standalone workflow behavior checks and test doubles.
 *
 * @author PublishPress
 * @copyright Copyright (c) 2026, PublishPress
 * @license GPL v2 or later
 * @since 0.1.0
 */

declare(strict_types=1);

use WRDQ\LocalStorage;
use WRDQ\Mailer;
use WRDQ\Repository;
use WRDQ\Workflow;

// This standalone runner intentionally keeps its global WordPress stubs and test doubles in one file.
// phpcs:disable PSR1.Files.SideEffects, PSR1.Classes.ClassDeclaration
// Repository test doubles must retain the interface's method names.
// phpcs:disable PSR1.Methods.CamelCapsMethodName

/**
 * Translate a string using the standalone test dictionary.
 *
 * @since 0.1.0
 */
function __(string $text, string $domain): string
{
    if ($domain !== 'wp-reliable-document-queue') {
        throw new RuntimeException('Unexpected translation domain: ' . $domain);
    }
    return $GLOBALS['wrdq_test_translations'][$text] ?? $text;
}

foreach (['Repository', 'Storage', 'Mailer', 'Workflow'] as $class) {
    require __DIR__ . '/../src/' . $class . '.php';
}
require __DIR__ . '/../src/LocalStorage.php';

/**
 * Translate and escape a string without loading WordPress.
 *
 * @since 0.1.0
 */
function esc_html__(string $text, string $domain): string
{
    return htmlspecialchars(__($text, $domain), ENT_QUOTES, 'UTF-8');
}

/**
 * In-memory document and delivery persistence for workflow checks.
 */
final class MemoryRepository implements Repository
{
    public array $docs = [];
    public array $mail = [];

    /**
     * Create a document record if it does not exist.
     *
     * @since 0.1.0
     */
    public function ensure(string $key): void
    {
        $this->docs[$key] ??= ['status' => 'pending', 'path' => null];
    }

    /**
     * Read a document record.
     *
     * @since 0.1.0
     */
    public function get(string $key): array
    {
        return $this->docs[$key];
    }

    /**
     * Claim pending work or recover an expired lease.
     *
     * @since 0.1.0
     */
    public function claim(string $key, int $now, int $leaseSeconds): ?string
    {
        $document = &$this->docs[$key];
        if ($document['status'] !== 'pending' && !($document['status'] === 'processing' && $document['lease'] < $now)) {
            return null;
        }
        $document['status'] = 'processing';
        $document['lease'] = $now + $leaseSeconds;
        $document['token'] = bin2hex(random_bytes(8));
        return $document['token'];
    }

    /**
     * Commit a document for the current claim holder.
     *
     * @since 0.1.0
     */
    public function complete(string $key, string $token, string $hash, string $path): bool
    {
        $document = &$this->docs[$key];
        if ($document['token'] !== $token) {
            return false;
        }
        $document['status'] = 'ready';
        $document['path'] = $path;
        $document['hash'] = $hash;
        return true;
    }

    /**
     * Create a delivery record if it does not exist.
     *
     * @since 0.1.0
     */
    public function ensure_delivery(string $key): void
    {
        $this->mail[$key] ??= 'pending';
    }

    /**
     * Claim a delivery only once.
     *
     * @since 0.1.0
     */
    public function claim_delivery(string $key, int $now): bool
    {
        if ($this->mail[$key] !== 'pending') {
            return false;
        }
        $this->mail[$key] = 'sending';
        return true;
    }

    /**
     * Record an accepted delivery.
     *
     * @since 0.1.0
     */
    public function mark_delivered(string $key): void
    {
        $this->mail[$key] = 'accepted';
    }

    /**
     * Record an ambiguous delivery outcome.
     *
     * @since 0.1.0
     */
    public function mark_uncertain(string $key): void
    {
        $this->mail[$key] = 'uncertain';
    }
}

/**
 * Mail transport that counts calls without sending email.
 */
final class FakeMailer implements Mailer
{
    public int $calls = 0;

    /**
     * Count a simulated successful delivery.
     *
     * @since 0.1.0
     */
    public function send(string $key, string $path): bool
    {
        $this->calls++;
        return true;
    }
}

/**
 * Report a passing assertion or fail the test run.
 *
 * @since 0.1.0
 */
function check(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }

    echo "PASS: $message\n";
}

$dir = sys_get_temp_dir() . '/wrdq-test-' . bin2hex(random_bytes(5));
$r = new MemoryRepository();
$m = new FakeMailer();
$storage = new LocalStorage($dir);
$w = new Workflow($r, $storage, $m, 10);
check($w->render('abc', 100) === 'ready', 'first render completes');
check($w->render('abc', 101) === 'already_ready', 'duplicate event does not regenerate');
check(count($r->docs) === 1, 'one document identity');
check($w->deliver('abc', 102) === 'accepted', 'first mail accepted');
check($w->deliver('abc', 103) === 'already_claimed' && $m->calls === 1, 'duplicate email skipped');

// Simulate crash after claim but before file/database commit.
$r->ensure('crash');
$r->claim('crash', 100, 10);
check($w->render('crash', 105) === 'busy', 'active lease blocks duplicate worker');
check($w->render('crash', 111) === 'ready', 'expired lease recovers');
check($w->render('crash', 112) === 'already_ready', 'recovered document remains unique');

// Simulate upload succeeded but process crashed before committing database row.
$r->ensure('stored');
$r->claim('stored', 100, 10);
[$hash, $path] = $storage->put_or_verify('stored', "Fictional document\nEvent: stored\n");
check($w->render('stored', 111) === 'ready', 'stored file reused on retry');
check(hash_file('sha256', $path) === $hash, 'recovered file hash verified');

// Simulate uncertain delivery handoff, no automatic retry.
$r->ensure('ambiguous');
$w->render('ambiguous', 120);
$r->claim_delivery('ambiguous', 121);
check($w->deliver('ambiguous', 122) === 'already_claimed', 'ambiguous handoff not resent');
check($m->calls === 1, 'no second send after ambiguous handoff');

// Exercise translated document contents and exceptions without WordPress.
$GLOBALS['wrdq_test_translations'] = [
    "Fictional document\nEvent: %s\n" => "Document fictif\nEvenement : %s\n",
    'Invalid event key' => 'Identifiant invalide',
];
check($w->render('translated', 130) === 'ready', 'translated document renders');
check(
    file_get_contents($r->docs['translated']['path']) === "Document fictif\nEvenement : translated\n",
    'document template uses translations and preserves event key'
);
try {
    $w->render('', 131);
    check(false, 'invalid event key rejected');
} catch (InvalidArgumentException $error) {
    check($error->getMessage() === 'Identifiant invalide', 'exception uses translation');
}
unset($GLOBALS['wrdq_test_translations']);

foreach (glob($dir . '/*') as $file) {
    unlink($file);
}
rmdir($dir);
echo "All checks passed.\n";
