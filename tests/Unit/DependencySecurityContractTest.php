<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

test('dependency scans cover both locked graphs without weakening the deterministic quality contract', function () {
    $root = dirname(__DIR__, 2);
    $composer = json_decode(file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    expect($composer['scripts']['ci:check'])->toBe(['@quality:backend', '@quality:frontend', '@test:isolated']);
    expect($composer['scripts']['security:check'])->toBe(['@security:composer', '@security:npm']);
    expect($composer['scripts']['security:composer'])->toBe([
        '@ci:environment', '@composer --no-plugins --no-scripts audit --locked --abandoned=report --no-interaction',
    ]);
    expect($composer['scripts']['security:npm'])->toBe([
        '@ci:environment', 'npm audit --package-lock-only --include=prod --include=dev --include=optional --include=peer --audit-level=info --ignore-scripts --json',
    ]);
    expect($composer['config'])->not->toHaveKeys(['audit', 'policy']);
});

test('Dependabot retains action updates and bounds weekly Composer and npm version PRs without security ignores', function () {
    $configuration = Yaml::parseFile(dirname(__DIR__, 2).'/.github/dependabot.yml');
    expect($configuration['version'])->toBe(2);
    $updates = array_column($configuration['updates'], null, 'package-ecosystem');
    expect(array_keys($updates))->toEqualCanonicalizing(['github-actions', 'composer', 'npm']);
    expect($updates['github-actions']['cooldown']['default-days'])->toBe(5);
    expect($updates['github-actions']['groups']['github-actions']['patterns'])->toBe(['*']);
    foreach (['composer', 'npm'] as $ecosystem) {
        $update = $updates[$ecosystem];
        expect($update['directory'])->toBe('/');
        expect($update['schedule']['interval'])->toBe('weekly');
        expect($update['open-pull-requests-limit'])->toBe(3);
        expect($update)->not->toHaveKeys(['ignore', 'target-branch', 'cooldown']);
        foreach (['production', 'development'] as $type) {
            expect($update['groups'][$ecosystem.'-'.$type])->toBe([
                'applies-to' => 'version-updates', 'dependency-type' => $type, 'patterns' => ['*'], 'update-types' => ['minor', 'patch'],
            ]);
        }
    }
});

test('the security job remains an unconditional prerequisite of the read only final gate', function () {
    $workflow = Yaml::parseFile(dirname(__DIR__, 2).'/.github/workflows/tests.yml');
    expect($workflow['permissions'])->toBe(['contents' => 'read']);
    expect($workflow['jobs']['ci']['if'])->toBe('always()');
    expect($workflow['jobs']['ci']['needs'])->toEqualCanonicalizing(['backend-quality', 'frontend-quality', 'isolated-tests', 'dependency-security', 'source-security', 'secret-security', 'postgres-integration', 'browser-accessibility', 'financial-e2e']);
    $job = $workflow['jobs']['dependency-security'];
    expect($job)->not->toHaveKeys(['if', 'continue-on-error']);
    $commands = array_column($job['steps'], 'run');
    expect($commands)->toContain('composer security:check', 'composer install --no-interaction --prefer-dist --no-progress --no-scripts', 'git diff --exit-code HEAD');
    foreach ($job['steps'] as $step) {
        expect($step)->not->toHaveKey('continue-on-error');
        if (($step['run'] ?? null) === 'composer security:check') {
            expect($step)->not->toHaveKey('if');
        }
    }
});

dataset('required CI job outcomes', [
    'all succeed' => [null, 'success'],
    ...array_merge(...array_map(static fn (string $job): array => array_combine(
        array_map(static fn (string $state): string => $job.' '.$state, ['failure', 'cancelled', 'skipped', 'unknown', '']),
        array_map(static fn (string $state): array => [$job, $state], ['failure', 'cancelled', 'skipped', 'unknown', '']),
    ), ['BACKEND_RESULT', 'FRONTEND_RESULT', 'TEST_RESULT', 'SECURITY_RESULT', 'SOURCE_RESULT', 'SECRET_RESULT', 'POSTGRES_RESULT', 'BROWSER_RESULT', 'E2E_RESULT'])),
]);

test('the actual hosted gate rejects every missing or unsuccessful required job result', function (?string $job, string $state) {
    $workflow = Yaml::parseFile(dirname(__DIR__, 2).'/.github/workflows/tests.yml');
    $steps = array_column($workflow['jobs']['ci']['steps'], null, 'name');
    $gate = $steps['Require every quality job to succeed'];
    expect($gate['env'])->toBe([
        'BACKEND_RESULT' => '${{ needs.backend-quality.result }}',
        'FRONTEND_RESULT' => '${{ needs.frontend-quality.result }}',
        'TEST_RESULT' => '${{ needs.isolated-tests.result }}',
        'SECURITY_RESULT' => '${{ needs.dependency-security.result }}',
        'SOURCE_RESULT' => '${{ needs.source-security.result }}',
        'SECRET_RESULT' => '${{ needs.secret-security.result }}',
        'POSTGRES_RESULT' => '${{ needs.postgres-integration.result }}',
        'BROWSER_RESULT' => '${{ needs.browser-accessibility.result }}',
        'E2E_RESULT' => '${{ needs.financial-e2e.result }}',
    ]);
    $command = trim($gate['run']);
    $prefix = "php -d memory_limit=64M -r '";
    expect($command)->toStartWith($prefix)->toEndWith("'");
    $code = substr($command, strlen($prefix), -1);
    $environment = array_fill_keys(['BACKEND_RESULT', 'FRONTEND_RESULT', 'TEST_RESULT', 'SECURITY_RESULT', 'SOURCE_RESULT', 'SECRET_RESULT', 'POSTGRES_RESULT', 'BROWSER_RESULT', 'E2E_RESULT'], 'success');
    if ($job !== null) {
        $environment[$job] = $state === '' ? false : $state;
    }
    $process = new Process([PHP_BINARY, '-d', 'memory_limit=64M', '-r', $code], dirname(__DIR__, 2), $environment);
    $process->setTimeout(10);
    $process->run();

    expect($process->getExitCode())->toBe($job === null ? 0 : 1, $process->getErrorOutput());
})->with('required CI job outcomes');

test('native lockfile audits propagate vulnerable reports and advisory outages through the Composer security command', function () {
    $root = dirname(__DIR__, 2);
    $source = json_decode(file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $directory = sys_get_temp_dir().'/intelledger-security-'.bin2hex(random_bytes(8));
    mkdir($directory);
    $server = null;
    try {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorNumber, $errorMessage);
        if ($socket === false) {
            throw new RuntimeException('Unable to reserve the isolated advisory-server port.');
        }
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        if (! is_string($address)) {
            throw new RuntimeException('Unable to resolve the isolated advisory-server address.');
        }
        $url = 'http://'.$address;
        $router = $directory.'/router.php';
        file_put_contents($router, <<<'PHP'
<?php
$mode = json_decode(file_get_contents(__DIR__.'/mode.json'), true, flags: JSON_THROW_ON_ERROR);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$url = 'http://'.$_SERVER['HTTP_HOST'];
file_put_contents(__DIR__.'/requests.log', $_SERVER['REQUEST_METHOD'].' '.$path.' '.$mode['composer'].' '.$mode['npm']."\n", FILE_APPEND);
header('Content-Type: application/json');
if (($mode['composer'] === 'unavailable' && in_array($path, ['/packages.json', '/composer-advisories'], true))
    || ($mode['npm'] === 'unavailable' && str_starts_with($path, '/-/npm/'))) {
    http_response_code(503);
    echo json_encode(['error' => 'Synthetic advisory service unavailable']);
} elseif ($path === '/packages.json') {
    echo json_encode(['packages' => [], 'metadata-url' => $url.'/p2/%package%.json', 'available-packages' => ['fixture/library'], 'security-advisories' => ['api-url' => $url.'/composer-advisories', 'metadata' => false]]);
} elseif ($path === '/composer-advisories') {
    echo json_encode(['advisories' => ['fixture/library' => $mode['composer'] === 'vulnerable' ? [[
        'advisoryId' => 'PKSA-test-test-test', 'packageName' => 'fixture/library',
        'title' => 'Synthetic dependency audit probe', 'link' => 'https://example.invalid/advisory',
        'cve' => null, 'affectedVersions' => '<1.0.1', 'severity' => 'low',
        'reportedAt' => '2026-10-28T00:00:00+00:00', 'sources' => [],
    ]] : []]]);
} elseif ($path === '/-/npm/v1/security/advisories/bulk') {
    echo json_encode($mode['npm'] === 'clean' ? new stdClass : ['fixture-library' => [[
        'id' => 10000001, 'title' => 'Synthetic dependency audit probe',
        'url' => 'https://example.invalid/advisory', 'severity' => $mode['npm'],
        'vulnerable_versions' => '<1.0.1', 'cwe' => ['CWE-79'],
        'cves' => [], 'cvss' => ['score' => 3.1, 'vectorString' => null],
    ]]]);
} elseif ($path === '/fixture-library') {
    echo json_encode(['name' => 'fixture-library', 'dist-tags' => ['latest' => '1.0.1'], 'versions' => [
        '1.0.0' => ['name' => 'fixture-library', 'version' => '1.0.0'],
        '1.0.1' => ['name' => 'fixture-library', 'version' => '1.0.1'],
    ]]);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Unexpected synthetic advisory request']);
}
PHP);
        $fixture = [
            'name' => 'intelledger/security-probe', 'version' => '1.0.0',
            'require-dev' => ['fixture/library' => '1.0.0'],
            'config' => ['secure-http' => false, 'vendor-dir' => $root.'/vendor', 'allow-plugins' => false],
            'autoload-dev' => ['psr-4' => ['Tests\\' => $root.'/tests/']],
            'repositories' => ['fixture' => ['type' => 'composer', 'url' => $url], 'packagist.org' => false],
            'scripts' => array_intersect_key($source['scripts'], array_flip(['security:check', 'security:composer', 'security:npm', 'ci:environment'])),
        ];
        $preflight = $directory.'/preflight.php';
        file_put_contents($preflight, '<?php require '.var_export($root.'/vendor/autoload.php', true).'; Tests\\Support\\QualityEnvironment::configure();');
        $fixture['scripts']['ci:environment'] = ['@php "'.$preflight.'"'];
        file_put_contents($directory.'/composer.json', json_encode($fixture, JSON_THROW_ON_ERROR));
        file_put_contents($directory.'/package.json', json_encode([
            'name' => 'security-probe', 'version' => '1.0.0', 'private' => true, 'devDependencies' => ['fixture-library' => '1.0.0'],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($directory.'/package-lock.json', json_encode([
            'name' => 'security-probe', 'version' => '1.0.0', 'lockfileVersion' => 3, 'requires' => true,
            'packages' => [
                '' => ['name' => 'security-probe', 'version' => '1.0.0', 'devDependencies' => ['fixture-library' => '1.0.0']],
                'node_modules/fixture-library' => ['version' => '1.0.0', 'dev' => true, 'resolved' => $url.'/fixture-library.tgz'],
            ],
        ], JSON_THROW_ON_ERROR));
        $server = new Process([PHP_BINARY, '-d', 'memory_limit=128M', '-S', $address, $router], $directory);
        $server->start();
        $ready = false;
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $connection = @stream_socket_client('tcp://'.$address, $errorNumber, $errorMessage, 0.1);
            if ($connection !== false) {
                fclose($connection);
                $ready = true;
                break;
            }
            usleep(20_000);
        }
        expect($ready)->toBeTrue($server->getErrorOutput());
        foreach ([
            'clean' => ['clean', 'clean', false],
            'Composer low vulnerability' => ['vulnerable', 'clean', false],
            'npm informational vulnerability' => ['clean', 'info', false],
            'npm high vulnerability' => ['clean', 'high', false],
            'abandoned warning' => ['clean', 'clean', true],
            'Composer outage' => ['unavailable', 'clean', false],
            'npm outage' => ['clean', 'unavailable', false],
        ] as $case => [$composerMode, $npmMode, $abandoned]) {
            file_put_contents($directory.'/mode.json', json_encode(['composer' => $composerMode, 'npm' => $npmMode], JSON_THROW_ON_ERROR));
            file_put_contents($directory.'/composer.lock', json_encode([
                'packages' => [], 'packages-dev' => [['name' => 'fixture/library', 'version' => '1.0.0', 'type' => 'library', 'abandoned' => $abandoned]],
                'aliases' => [], 'minimum-stability' => 'stable', 'stability-flags' => [],
                'prefer-stable' => true, 'prefer-lowest' => false, 'platform' => [], 'platform-dev' => [], 'plugin-api-version' => '2.9.0',
            ], JSON_THROW_ON_ERROR));
            $before = hash_file('sha256', $directory.'/composer.lock').hash_file('sha256', $directory.'/package-lock.json');
            $process = new Process(['composer', 'security:check', '--no-interaction'], $directory, [
                'PAO_DISABLE' => '1', 'COMPOSER_HOME' => $directory.'/home',
                'COMPOSER_CACHE_DIR' => $directory.'/cache/'.$case.'/composer',
                'NODE_ENV' => 'production', 'npm_config_omit' => 'dev',
                'npm_config_registry' => $url, 'npm_config_cache' => $directory.'/cache/'.$case.'/npm',
                'npm_config_fetch_retries' => '0', 'npm_config_fetch_timeout' => '3000',
            ]);
            $process->setTimeout(90);
            $process->run();
            $output = $process->getOutput().$process->getErrorOutput().file_get_contents($directory.'/requests.log');
            if (str_contains($output, 'npm error')) {
                foreach (glob($directory.'/cache/'.$case.'/npm/_logs/*debug-0.log') as $log) {
                    $output .= implode("\n", array_filter(explode("\n", file_get_contents($log)), static fn (string $line): bool => str_contains($line, 'verbose stack')));
                }
            }
            if ($case === 'clean' || $abandoned) {
                expect($process->getExitCode())->toBe(0, $case.': '.$output);
            } else {
                expect($process->getExitCode())->not->toBe(0, $case.': '.$output);
            }
            if ($composerMode === 'vulnerable') {
                expect($output)->toContain('PKSA-test-test-test', 'security vulnerability');
            }
            if (in_array($npmMode, ['info', 'high'], true)) {
                expect($output)->toContain('Synthetic dependency audit probe');
                $jsonStart = strpos($process->getOutput(), '{');
                expect($jsonStart)->not->toBeFalse();
                $report = json_decode(substr($process->getOutput(), $jsonStart), true, flags: JSON_THROW_ON_ERROR);
                expect($report['auditReportVersion'])->toBe(2);
                expect($report['metadata']['vulnerabilities'][$npmMode])->toBe(1);
            }
            if ($abandoned) {
                expect($output)->toContain('abandoned');
            }
            if ($composerMode === 'unavailable' || $npmMode === 'unavailable') {
                expect($output)->toContain('503');
            }
            expect(hash_file('sha256', $directory.'/composer.lock').hash_file('sha256', $directory.'/package-lock.json'))->toBe($before);
        }
    } finally {
        $server?->stop(1);
        $resolved = realpath($directory);
        if ($resolved === false || dirname($resolved) !== realpath(sys_get_temp_dir()) || ! str_starts_with(basename($resolved), 'intelledger-security-')) {
            throw new RuntimeException('Refusing cleanup outside the isolated security-probe directory.');
        }
        $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($entries as $entry) {
            if ($entry->isDir() && ! $entry->isLink()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($resolved);
    }
});
