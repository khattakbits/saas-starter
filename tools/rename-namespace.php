<?php

/**
 * Namespace rename helper for the package extraction
 * (docs/platform/03-EXTRACTION-PLAN.md, step B4).
 *
 * Usage:
 *   php tools/rename-namespace.php 'App\Core\Support\Naming' 'KhattakBits\Core\Support\Naming'
 *   php tools/rename-namespace.php 'App\Core\Support\Naming' 'KhattakBits\Core\Support\Naming' --write
 *   php tools/rename-namespace.php 'App\Core' 'KhattakBits\Core' --write --paths=app,routes
 *
 * Default is a dry run: it prints every file that would change and how many
 * replacements it holds. With --write each touched file is backed up to
 * "<file>.bak" before being rewritten, so a bad run is one `mv` away from undo.
 */
$from = $argv[1] ?? null;
$to = $argv[2] ?? null;

if ($from === null || $to === null) {
    fwrite(STDERR, "usage: php tools/rename-namespace.php <from> <to> [--write] [--paths=app,routes,...]\n");
    exit(1);
}

$write = in_array('--write', $argv, true);
$paths = ['app', 'routes', 'config', 'database', 'tests', 'bootstrap'];

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--paths=')) {
        $paths = array_filter(array_map('trim', explode(',', substr($arg, 8))));
    }
}

$totalFiles = 0;
$totalHits = 0;

foreach ($paths as $path) {
    if (! is_dir($path) && ! is_file($path)) {
        fwrite(STDERR, "skip (missing): {$path}\n");

        continue;
    }

    $iterator = is_file($path)
        ? [new SplFileInfo($path)]
        : new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $filePath = $file->getPathname();
        $code = file_get_contents($filePath);
        $new = str_replace($from, $to, $code, $count);

        if ($count === 0) {
            continue;
        }

        $totalFiles++;
        $totalHits += $count;
        printf("%-72s %d\n", $filePath, $count);

        if ($write) {
            copy($filePath, $filePath.'.bak');
            file_put_contents($filePath, $new);
        }
    }
}

printf("\n%s %d replacement(s) in %d file(s)\n", $write ? 'WROTE' : 'DRY-RUN:', $totalHits, $totalFiles);

if ($write) {
    echo "Backups: *.bak — delete them once `php artisan test` is green.\n";
}
