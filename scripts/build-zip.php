<?php
/**
 * Cross-platform distribution builder. Requires PHP ZipArchive, Composer and Git.
 *
 * Usage: php scripts/build-zip.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$pluginName = 'wp-reliable-document-queue';
$header = file_get_contents($root . '/document-queue-demo.php');
if (!preg_match('/^ \* Version: (\d+\.\d+\.\d+)\s*$/m', $header, $matches)) {
    fwrite(STDERR, "Invalid plugin version header.\n");
    exit(1);
}
if (!class_exists(ZipArchive::class)) {
    fwrite(STDERR, "PHP ext-zip is required (ZipArchive). Enable it in your CLI php.ini.\n");
    exit(1);
}
$version = $matches[1];
$files = [
    'document-queue-demo.php',
    'composer.json',
    'composer.lock',
    'src/Installer.php',
    'src/Plugin.php',
    'src/Repository.php',
    'src/Storage.php',
    'src/Mailer.php',
    'src/Workflow.php',
    'src/LocalStorage.php',
    'src/WordPressRepository.php',
    'src/WordPressMailer.php',
];
$run = static function (array $args, string $cwd): void {
    if (PHP_OS_FAMILY === 'Windows' && $args[0] === 'composer') {
        $quoted = array_map(
            static fn (string $value): string => "'" . str_replace("'", "''", $value) . "'",
            array_slice($args, 1)
        );
        $command = "& composer " . implode(' ', $quoted);
        $args = ['powershell.exe', '-NoProfile', '-NonInteractive', '-Command', $command];
    }
    $process = proc_open(
        $args,
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $cwd
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start command: ' . implode(' ', $args));
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    if ($stdout !== '') {
        echo $stdout;
    }
    if ($stderr !== '') {
        fwrite(STDERR, $stderr);
    }
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Command failed: ' . implode(' ', $args));
    }
};
$work = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wrdq-build-' . bin2hex(random_bytes(8));
$stage = $work . DIRECTORY_SEPARATOR . $pluginName;
$cleanup = static function (string $directory) use (&$cleanup): void {
    if (!is_dir($directory)) {
        return;
    }
    foreach (new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS) as $item) {
        if ($item->isDir() && !$item->isLink()) {
            $cleanup($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($directory);
};
try {
    if (!mkdir($stage, 0700, true)) {
        throw new RuntimeException('Cannot create staging directory.');
    }
    foreach ($files as $file) {
        $destination = $stage . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
        $directory = dirname($destination);
        if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
            throw new RuntimeException('Cannot create directory: ' . $directory);
        }
        $command = ['git', 'show', 'HEAD:' . $file];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $destination, 'w'], 2 => STDERR], $pipes, $root);
        if (!is_resource($process) || proc_close($process) !== 0) {
            throw new RuntimeException('Cannot read committed file: ' . $file . '. Commit source and composer.lock first.');
        }
    }
    $run(['composer', 'install', '--working-dir=' . $stage, '--no-dev', '--no-interaction', '--prefer-dist', '--no-progress', '--optimize-autoloader'], $root);
    if (!is_file($stage . '/vendor/autoload.php') ||
        !is_file($stage . '/vendor/woocommerce/action-scheduler/action-scheduler.php')) {
        throw new RuntimeException('Missing required production dependencies.');
    }
    unlink($stage . '/composer.json');
    unlink($stage . '/composer.lock');
    $dist = $root . DIRECTORY_SEPARATOR . 'dist';
    if (!is_dir($dist) && !mkdir($dist, 0700, true)) {
        throw new RuntimeException('Cannot create dist directory.');
    }
    // Remove only previously generated ZIPs for this plugin. Preserve all
    // unrelated files in dist and leave non-ZIP artifacts untouched.
    foreach (new DirectoryIterator($dist) as $item) {
        if (
            $item->isFile()
            && preg_match('/^' . preg_quote($pluginName, '/') . '-[0-9]+\\.[0-9]+\\.[0-9]+\\.zip$/', $item->getFilename())
        ) {
            if (!unlink($item->getPathname())) {
                throw new RuntimeException('Could not remove old distribution: ' . $item->getFilename());
            }
        }
    }
    $archive = $dist . DIRECTORY_SEPARATOR . $pluginName . '-' . $version . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Cannot open ZIP for writing.');
    }
    $zip->addEmptyDir($pluginName);
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($item->getPathname(), strlen($stage) + 1));
        if (preg_match('~(^|/)(tests?|\.github|\.git|bin)(/|$)~', $relative)) {
            continue;
        }
        $entry = $pluginName . '/' . $relative;
        if ($item->isDir()) {
            $zip->addEmptyDir($entry);
        } else {
            $zip->addFile($item->getPathname(), $entry);
        }
    }
    if (!$zip->close()) {
        throw new RuntimeException('Cannot finalize ZIP.');
    }
    $check = new ZipArchive();
    if ($check->open($archive) !== true) {
        throw new RuntimeException('Cannot verify ZIP.');
    }
    foreach ([
        $pluginName . '/document-queue-demo.php',
        $pluginName . '/vendor/autoload.php',
        $pluginName . '/vendor/woocommerce/action-scheduler/action-scheduler.php',
    ] as $required) {
        if ($check->locateName($required) === false) {
            throw new RuntimeException('Missing ZIP entry: ' . $required);
        }
    }
    $check->close();
    echo "Built: " . $archive . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
} finally {
    $cleanup($work);
}
