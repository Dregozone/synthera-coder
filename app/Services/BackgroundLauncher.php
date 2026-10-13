<?php

namespace App\Services;

use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Starts an Artisan command in its own process and returns at once, so a long
 * job (a `composer update`) is not tied to the web request that asked for it
 * and its time limits. No queue worker is needed. Works on Windows (Herd) and
 * on macOS/Linux.
 */
class BackgroundLauncher
{
    /**
     * @param  list<string>  $arguments  e.g. ['synthera:run-command', '12']
     */
    public function artisan(array $arguments): void
    {
        $command = implode(' ', array_map(escapeshellarg(...), [$this->php(), base_path('artisan'), ...$arguments]));

        if (PHP_OS_FAMILY === 'Windows') {
            // `start /B` detaches it; the empty title stops `start` reading the first quoted argument as one.
            pclose(popen('start "" /B '.$command.' > NUL 2>&1', 'r'));

            return;
        }

        exec($command.' > /dev/null 2>&1 &');
    }

    /**
     * The PHP command-line binary: as configured, else the one running now (the
     * CLI beside it when this is a web server's CGI/FPM build).
     */
    public function php(): string
    {
        $configured = config('synthera-coder.php_binary');

        if (filled($configured)) {
            return (string) $configured;
        }

        $found = (new PhpExecutableFinder)->find(false) ?: 'php';

        if (preg_match('/php-(cgi|fpm)[\d.]*(\.exe)?$/i', $found, $match) === 1) {
            $cli = dirname($found).DIRECTORY_SEPARATOR.'php'.($match[2] ?? '');

            if (is_file($cli)) {
                return $cli;
            }
        }

        return $found;
    }
}
