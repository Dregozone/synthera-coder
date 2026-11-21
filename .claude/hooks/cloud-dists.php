<?php

/*
 * Claude Code cloud sessions only (Life OS's cloud worker, docs/office/30 in life-os), called by
 * cloud-setup.sh when `composer install` can't download packages. Kept by Life OS
 * (docs/office/cloud-worker/cloud-dists.php); edit it there.
 *
 * Why: in a cloud session, GitHub is reached through a proxy that only serves the repositories
 * attached to the session, so Composer's zips of other people's packages
 * (api.github.com/.../zipball, codeload.github.com) are refused. Git clones of them work, so
 * cloud-setup.sh retries with --prefer-source. A few packages (phpstan/phpstan) only come as zips,
 * with no git source in composer.lock: for those, this builds the same zip from the locked commit
 * with git, and writes composer.cloud.json / composer.cloud.lock, copies whose lock points at the
 * local zips. The real composer.json and composer.lock are never changed.
 *
 * Prints how many zips it made (0: nothing to do, no copies written).
 */
$lockFile = 'composer.lock';
if (! is_file($lockFile) || ! is_file('composer.json')) {
    echo 0;
    exit(0);
}
$lock = json_decode(file_get_contents($lockFile), true);
if (! is_array($lock)) {
    echo 0;
    exit(0);
}

/** Remove a folder and everything in it (git's files are read-only on some systems). */
function removeTree(string $path): void
{
    if (! file_exists($path)) {
        return;
    }
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) {
        @chmod($item->getPathname(), 0777);
        $item->isDir() && ! $item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

$dir = sys_get_temp_dir().'/cloud-dists';
@mkdir($dir, 0777, true);
$made = 0;

foreach (['packages', 'packages-dev'] as $group) {
    foreach ($lock[$group] ?? [] as $i => $package) {
        if (! empty($package['source']['url'])) {
            continue;
        }
        $url = $package['dist']['url'] ?? '';
        if (! preg_match('~^https://api\.github\.com/repos/([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+)/zipball/([0-9a-f]{40})$~', $url, $m)) {
            continue;
        }
        [, $owner, $repo, $sha] = $m;
        $zip = "{$dir}/{$owner}-{$repo}-{$sha}.zip";
        if (! is_file($zip)) {
            $src = "{$dir}/src-{$owner}-{$repo}";
            $q = fn (string $s): string => escapeshellarg($s);
            removeTree($src);
            $cmd = 'git init -q '.$q($src)
                .' && git -C '.$q($src).' fetch -q --depth 1 '.$q("https://github.com/{$owner}/{$repo}.git").' '.$q($sha)
                .' && git -C '.$q($src).' archive --format=zip -o '.$q($zip).' FETCH_HEAD';
            $out = [];
            exec($cmd.' 2>&1', $out, $code);
            removeTree($src);
            if ($code !== 0 || ! is_file($zip)) {
                fwrite(STDERR, "Could not build {$package['name']} from git: ".implode("\n", $out)."\n");

                continue;
            }
        }
        $lock[$group][$i]['dist'] = ['type' => 'zip', 'url' => $zip, 'reference' => $sha, 'shasum' => ''];
        $made++;
    }
}

if ($made > 0) {
    copy('composer.json', 'composer.cloud.json');
    file_put_contents('composer.cloud.lock', json_encode($lock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
}
echo $made;
