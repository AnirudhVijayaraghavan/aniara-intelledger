<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$root = dirname(__DIR__, 2);
$python = $root.'/.phpunit.cache/semgrep-venv/'.(PHP_OS_FAMILY === 'Windows' ? 'Scripts/python.exe' : 'bin/python');
$script = match ($argv[1] ?? null) {
    'scan' => __DIR__.'/source_security.py',
    'test' => dirname(__DIR__).'/SourceSecurityTest.py',
    default => null,
};

if ($script === null || ! is_file($python)) {
    fwrite(STDERR, 'Install the pinned source-security environment documented in ENGINEERING.md.'.PHP_EOL);
    exit(2);
}

try {
    $process = new Process([$python, '-B', $script], $root);
    $process->setTimeout(300);
    $process->run(static function (string $type, string $output): void {
        fwrite($type === Process::ERR ? STDERR : STDOUT, $output);
    });
    exit($process->getExitCode() ?? 2);
} catch (Throwable) {
    fwrite(STDERR, 'Source-security process failed; no clean result.'.PHP_EOL);
    exit(2);
}
