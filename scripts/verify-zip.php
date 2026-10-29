<?php
/**
 * Verify that the distribution ZIP contains production files only.
 *
 * Run with: php scripts/verify-zip.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$header = file_get_contents($root . '/document-queue-demo.php');
if (!preg_match('/^ \* Version: (\d+\.\d+\.\d+)\s*$/m', $header, $matches)) {
    fwrite(STDERR, "Missing plugin version.\n");
    exit(1);
}
$plugin = 'wp-reliable-document-queue';
$archive = $root . '/dist/' . $plugin . '-' . $matches[1] . '.zip';
if (!is_file($archive)) {
    fwrite(STDERR, "Missing distribution ZIP: $archive\n");
    exit(1);
}
$zip = new ZipArchive();
if ($zip->open($archive) !== true) {
    fwrite(STDERR, "Cannot open ZIP.\n");
    exit(1);
}
$required = [
    "$plugin/document-queue-demo.php",
    "$plugin/vendor/autoload.php",
    "$plugin/vendor/woocommerce/action-scheduler/action-scheduler.php",
    "$plugin/src/Plugin.php",
    "$plugin/src/Workflow.php",
];
foreach ($required as $entry) {
    if ($zip->locateName($entry) === false) {
        fwrite(STDERR, "Missing runtime entry: $entry\n");
        exit(1);
    }
}
$forbidden = '~(^|/)(tests?|\.github|\.git|phpunit|php_codesniffer|wpcs|scripts)(/|$)|(^|/)composer\.(json|lock)$|(^|/)phpcs\.xml\.dist$~i';
for ($i = 0; $i < $zip->numFiles; ++$i) {
    $entry = $zip->getNameIndex($i);
    if (!str_starts_with($entry, $plugin . '/')) {
        fwrite(STDERR, "Unexpected ZIP root: $entry\n");
        exit(1);
    }
    if (preg_match($forbidden, $entry)) {
        fwrite(STDERR, "Development file included: $entry\n");
        exit(1);
    }
}
$zip->close();
echo "Verified production-only distribution: " . basename($archive) . PHP_EOL;
