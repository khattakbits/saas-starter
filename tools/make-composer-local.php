<?php

/**
 * Local development override for the private khattakbits packages
 * (docs/platform/09-DEPLOYMENT-GUIDE.md §6.1–§6.2).
 *
 * `composer.json` commits `type: vcs` repositories so CI and production can
 * install the private packages over HTTPS with a token. On a dev machine the
 * sibling checkout is faster and works offline, so this script rewrites those
 * `vcs` repositories into `type: path` ones and writes the result to
 * `composer.local.json` — a file that is gitignored and therefore never
 * committed.
 *
 * Usage:
 *   php tools/make-composer-local.php           # write composer.local.json
 *   php tools/make-composer-local.php --dry-run # print it, change nothing
 *
 * Then point Composer at the generated file. The COMPOSER variable is the same
 * on every shell, only how you set it differs — both forms are listed so the
 * command works on cmd, PowerShell and bash alike:
 *   bash:       COMPOSER=composer.local.json composer update khattakbits/core --with-dependencies
 *   cmd:        set COMPOSER=composer.local.json && composer update khattakbits/core --with-dependencies
 *   PowerShell: $env:COMPOSER='composer.local.json'; composer update khattakbits/core --with-dependencies
 *
 * `COMPOSER=composer.local.json` makes Composer use `composer.local.lock`
 * instead of the committed `composer.lock`, so local resolution never touches
 * the lock file that CI installs from. Both local files are gitignored.
 *
 * Delete `composer.local.json` to go back to the committed (vcs) definition.
 */
$root = dirname(__DIR__);
$source = $root.'/composer.json';
$target = $root.'/composer.local.json';
$dryRun = in_array('--dry-run', $argv, true);

if (! is_file($source)) {
    fwrite(STDERR, "composer.json not found at {$source}\n");
    exit(1);
}

$composer = json_decode((string) file_get_contents($source), true, 512, JSON_THROW_ON_ERROR);

$rewritten = [];

foreach ($composer['repositories'] ?? [] as $index => $repository) {
    if (($repository['type'] ?? null) !== 'vcs') {
        continue;
    }

    // https://github.com/khattakbits/core.git  ->  ../khattakbits-core
    $name = basename((string) parse_url($repository['url'], PHP_URL_PATH), '.git');
    $path = '../khattakbits-'.$name;

    if (! is_dir($root.'/'.$path)) {
        fwrite(STDERR, "warning: {$path} does not exist next to this app — the path repository will not resolve\n");
    }

    $composer['repositories'][$index] = [
        'type' => 'path',
        'url' => $path,
        'options' => ['symlink' => true],
    ];

    $rewritten[] = "{$repository['url']} -> {$path}";
}

if ($rewritten === []) {
    fwrite(STDERR, "nothing to rewrite: composer.json has no `vcs` repositories\n");
    exit(1);
}

$json = json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";

if ($dryRun) {
    echo $json;

    exit(0);
}

file_put_contents($target, $json);

echo "wrote composer.local.json\n";

foreach ($rewritten as $line) {
    echo "  {$line}\n";
}

echo "\nnext (pick your shell):\n";
echo "  bash:       COMPOSER=composer.local.json composer update --with-dependencies\n";
echo "  cmd:        set COMPOSER=composer.local.json && composer update --with-dependencies\n";
echo "  PowerShell: \$env:COMPOSER='composer.local.json'; composer update --with-dependencies\n";
