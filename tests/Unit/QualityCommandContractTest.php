<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

function qualityContractContents(string $path): string
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException('Unable to read a quality command contract file.');
    }

    return $contents;
}

/**
 * Follow the public quality graph, including npm aliases, so an accidental setup,
 * migration, or write-mode formatter cannot hide behind another script name.
 *
 * @param  array<string, string|list<string>>  $composerScripts
 * @param  array<string, string>  $npmScripts
 * @param  list<string>  $ancestors
 * @return list<string>
 */
function qualityCommandLeaves(array $composerScripts, array $npmScripts, string $name, array $ancestors = []): array
{
    if (in_array($name, $ancestors, true)) {
        throw new LogicException('Quality commands must not contain cycles.');
    }
    $ancestors[] = $name;
    $commands = str_starts_with($name, 'npm:')
        ? [$npmScripts[substr($name, 4)]]
        : $composerScripts[$name];
    $commands = is_array($commands) ? $commands : [$commands];
    $leaves = [];
    foreach ($commands as $command) {
        if (preg_match('/^@([a-z][a-z0-9:-]*)(?:$|\\s)/', $command, $match) === 1 && ! in_array($match[1], ['php', 'composer'], true)) {
            array_push($leaves, ...qualityCommandLeaves($composerScripts, $npmScripts, $match[1], $ancestors));
        } elseif (preg_match_all('/\\bnpm run ([a-z0-9:-]+)/', $command, $matches) > 0) {
            foreach ($matches[1] as $script) {
                array_push($leaves, ...qualityCommandLeaves($composerScripts, $npmScripts, 'npm:'.$script, $ancestors));
            }
        } else {
            $leaves[] = $command;
        }
    }

    return $leaves;
}

test('quality commands cannot reach application initialization or write mode formatters', function () {
    $root = dirname(__DIR__, 2);
    $composer = json_decode(qualityContractContents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $npm = json_decode(qualityContractContents($root.'/package.json'), true, flags: JSON_THROW_ON_ERROR);
    foreach (['ci:check', 'ci:browser', 'quality:browser', 'quality:backend', 'quality:frontend', 'test:isolated', 'security:check', 'source:check', 'secret:check'] as $script) {
        $commands = qualityCommandLeaves($composer['scripts'], $npm['scripts'], $script);
        expect($commands)->not->toBeEmpty();
        foreach ($commands as $command) {
            expect($command)->not->toMatch('/\\b(?:migrate(?::[a-z-]+)?|db:wipe|key:generate|config:clear|config:cache|setup)\\b|--(?:fix|write|parallel)\\b/');
            if (str_contains($command, 'vendor/bin/pint')) {
                expect($command)->toContain('--test');
            }
            if (str_contains($command, 'vendor/bin/rector')) {
                expect($command)->toContain('--dry-run', '--config=rector.php', '--memory-limit=512M');
            }
            if (str_contains($command, 'vp fmt')) {
                expect($command)->toContain('--check');
            }
        }
    }
    $isolated = $composer['scripts']['test:isolated'];
    expect($isolated[0])->toBe('@test:isolation');
    expect(implode(' ', qualityCommandLeaves($composer['scripts'], $npm['scripts'], 'ci:check')))->toContain('node --experimental-strip-types --test --test-concurrency=1');
});

test('CI installs locked dependencies without running application initialization and preserves pinned actions', function () {
    $root = dirname(__DIR__, 2);
    $workflow = qualityContractContents($root.'/.github/workflows/tests.yml');
    $npm = json_decode(qualityContractContents($root.'/package.json'), true, flags: JSON_THROW_ON_ERROR);
    expect($workflow)->not->toContain('composer setup', 'artisan migrate', 'artisan key:generate', 'config:clear', 'continue-on-error');
    expect($workflow)->toContain('composer install --no-interaction --prefer-dist --no-progress --no-scripts', 'npm ci --no-audit --no-fund', 'contents: read', 'persist-credentials: false', 'git diff --exit-code HEAD');
    preg_match_all('/uses:\\s*(\\S+)/', $workflow, $actions);
    foreach ($actions[1] as $action) {
        expect($action)->toMatch('/@[a-f0-9]{40}$/');
    }
    $version = substr($npm['packageManager'], strlen('npm@'));
    expect($workflow)->toContain("NPM_VERSION: '".$version."'");
});

test('browser accessibility is an explicit bounded independent required check', function () {
    $root = dirname(__DIR__, 2);
    $composer = json_decode(qualityContractContents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $npm = json_decode(qualityContractContents($root.'/package.json'), true, flags: JSON_THROW_ON_ERROR);
    $workflow = Yaml::parseFile($root.'/.github/workflows/tests.yml');

    expect($composer['scripts']['ci:browser'])->toBe(['@ci:check', '@quality:browser']);
    expect($composer['scripts']['quality:browser'])->toBe(['@ci:environment', 'npm run test:browser', 'npm run test:browser:probe']);
    expect($npm['scripts']['test:browser'])->toBe('playwright test');
    expect($npm['scripts']['test:browser:probe'])->toBe('node tests/Support/browser-failure-probe.mjs');
    expect(qualityCommandLeaves($composer['scripts'], $npm['scripts'], 'ci:check'))->not->toContain('playwright test');
    $job = $workflow['jobs']['browser-accessibility'];
    expect($workflow['jobs']['ci']['needs'])->toContain('browser-accessibility');
    expect($job['timeout-minutes'])->toBe(20);
    expect($job)->not->toHaveKeys(['services', 'if', 'continue-on-error']);
    $commands = array_column($job['steps'], 'run');
    expect($commands)->toContain('npm ci --no-audit --no-fund', 'node node_modules/@playwright/test/cli.js install --with-deps chromium firefox webkit', 'npm run test:browser', 'npm run test:browser:probe', 'git diff --exit-code HEAD');
    expect(implode("\n", $commands))->not->toMatch('/\b(?:php|composer|artisan|psql|migrate)\b/');
    foreach ($job['steps'] as $step) {
        if (in_array($step['run'] ?? null, ['npm run test:browser', 'npm run test:browser:probe'], true)) {
            expect($step)->not->toHaveKeys(['if', 'continue-on-error']);
        }
    }
});

test('financial E2E is explicitly opted in and remains outside the safe local quality command', function () {
    $root = dirname(__DIR__, 2);
    $composer = json_decode(qualityContractContents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $npm = json_decode(qualityContractContents($root.'/package.json'), true, flags: JSON_THROW_ON_ERROR);
    $workflow = Yaml::parseFile($root.'/.github/workflows/tests.yml');
    expect($npm['scripts']['test:e2e'])->toBe('composer test:e2e');
    expect($composer['scripts']['test:e2e'])->not->toContain('@ci:environment');
    expect(implode(' ', qualityCommandLeaves($composer['scripts'], $npm['scripts'], 'ci:check')))->not->toContain('FinancialE2E.php', 'test:e2e');
    $job = $workflow['jobs']['financial-e2e'];
    expect($workflow['jobs']['ci']['needs'])->toContain('financial-e2e');
    expect($job['env']['INTELLEDGER_FINANCIAL_E2E'])->toBe('disposable-financial-e2e-only');
    expect($job['env']['PG_TEST_DATABASE'])->toBe('intelledger_ci_bootstrap');
    expect($job['services']['postgres'])->toBe($workflow['jobs']['postgres-integration']['services']['postgres']);
    expect($job['timeout-minutes'])->toBe(20);
    expect($job)->not->toHaveKeys(['if', 'continue-on-error', 'permissions']);
    $commands = array_column($job['steps'], 'run');
    expect($commands)->toContain('composer frontend:build', 'composer test:e2e:probe', 'npm run test:e2e', 'git diff --exit-code HEAD');
    foreach ($job['steps'] as $step) {
        if (in_array($step['run'] ?? null, ['composer test:e2e:probe', 'npm run test:e2e'], true)) {
            expect($step)->not->toHaveKeys(['if', 'continue-on-error']);
        }
    }
});

test('backend quality enforces level eight once over the complete configured PHP scope', function () {
    $root = dirname(__DIR__, 2);
    $composer = json_decode(qualityContractContents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $npm = json_decode(qualityContractContents($root.'/package.json'), true, flags: JSON_THROW_ON_ERROR);
    $configuration = qualityContractContents($root.'/phpstan.neon');
    $analysis = array_values(array_filter(
        qualityCommandLeaves($composer['scripts'], $npm['scripts'], 'quality:backend'),
        fn (string $command): bool => str_contains($command, 'vendor/bin/phpstan analyse'),
    ));

    expect($configuration)->toMatch('/^\s+level:\s*8\s*$/m');
    foreach (['app/', 'bootstrap/app.php', 'config/', 'database/', 'routes/'] as $path) {
        expect($configuration)->toContain('- '.$path);
    }
    expect($analysis)->toHaveCount(1);
    expect($analysis[0])->toBe('@php -d memory_limit=512M vendor/bin/phpstan analyse --debug --no-progress --memory-limit=512M');
    expect($composer['scripts'])->not->toHaveKey('types:check:pilot');
    expect(substr_count($configuration, '- vendor/phpstan/phpstan-strict-rules/rules.neon'))->toBe(1);
    expect($configuration)->toMatch('/^\s+allRules:\s*false\s*$/m');
    expect($configuration)->toMatch('/^\s+strictFunctionCalls:\s*true\s*$/m');
    expect($configuration)->toMatch('/^\s+disallowedLooseComparison:\s*true\s*$/m');
    expect($composer['require-dev'])->toHaveKey('phpstan/phpstan-strict-rules');
});

test('manifest lockfile and CI agree on the supported PHP runtime line', function () {
    $root = dirname(__DIR__, 2);
    $composer = json_decode(qualityContractContents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $lock = json_decode(qualityContractContents($root.'/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
    $workflow = qualityContractContents($root.'/.github/workflows/tests.yml');

    expect($composer['require']['php'])->toBe('~8.4.1');
    expect($lock['platform']['php'])->toBe($composer['require']['php']);
    expect($workflow)->toContain("PHP_VERSION: '8.4'");
    expect($composer['config'])->not->toHaveKey('platform');
    expect($lock)->not->toHaveKey('platform-overrides');
});

test('the installed PHPStan configuration rejects unsafe calls and comparisons while accepting strict equivalents', function () {
    $root = dirname(__DIR__, 2);
    $directory = sys_get_temp_dir().'/intelledger-strict-'.bin2hex(random_bytes(8));
    mkdir($directory);
    $fixture = $directory.'/probe.php';
    try {
        file_put_contents($fixture, <<<'PHP'
<?php
namespace IntelLedgerStrictRuleProbe;
function unsafeLookup(mixed $value): bool { return in_array($value, [1]); }
function unsafeComparison(mixed $left, mixed $right): bool { return $left != $right; }
function safeLookup(mixed $value): bool { return in_array($value, [1], true); }
function safeComparison(mixed $left, mixed $right): bool { return $left !== $right; }
PHP);
        $bootstrap = <<<'PHP'
require 'vendor/autoload.php';
Tests\Support\QualityEnvironment::configure();
$analysis = new Symfony\Component\Process\Process([
    PHP_BINARY, '-d', 'memory_limit=256M', 'vendor/bin/phpstan', 'analyse', '--debug',
    '--no-progress', '--memory-limit=256M', '--error-format=json', $argv[1],
]);
$analysis->setTimeout(60);
$analysis->run();
echo $analysis->getOutput();
fwrite(STDERR, $analysis->getErrorOutput());
exit($analysis->getExitCode() ?? 1);
PHP;
        $process = new Process([PHP_BINARY, '-r', $bootstrap, $fixture], $root, ['PAO_DISABLE' => '1']);
        $process->setTimeout(75);
        $process->run();

        expect($process->getExitCode())->toBe(1, $process->getErrorOutput());
        $output = $process->getOutput();
        $jsonStart = strpos($output, '{');
        expect($jsonStart)->not->toBeFalse($output);
        $diagnostics = json_decode(substr($output, $jsonStart), true, flags: JSON_THROW_ON_ERROR);
        expect($diagnostics['totals'])->toBe(['errors' => 0, 'file_errors' => 2]);
        $messages = array_merge(...array_column($diagnostics['files'], 'messages'));
        expect(array_column($messages, 'identifier'))->toEqualCanonicalizing(['function.strict', 'notEqual.notAllowed']);
    } finally {
        if (is_file($fixture)) {
            unlink($fixture);
        }
        rmdir($directory);
    }
});

test('quality environment isolates inherited development settings without changing parent variables or configuration files', function () {
    $root = dirname(__DIR__, 2);
    $paths = [$root.'/.env', $root.'/bootstrap/cache/config.php', $root.'/tests/TestCase.php', $root.'/phpunit.xml'];
    $before = [];
    foreach ($paths as $path) {
        $before[$path] = is_file($path) ? hash_file('sha256', $path) : null;
    }
    $parentEnvironment = getenv('APP_ENV');
    $process = new Process([PHP_BINARY], $root, [
        'APP_ENV' => 'production', 'DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => 'must_not_open',
        'DB_URL' => 'pgsql://localhost/must_not_open', 'APP_CONFIG_CACHE' => 'bootstrap/cache/config.php',
    ]);
    $process->setInput(<<<'PHP'
<?php
require 'vendor/autoload.php';
Tests\Support\QualityEnvironment::configure();
echo json_encode([
    'environment' => getenv('APP_ENV'),
    'driver' => getenv('DB_CONNECTION'),
    'database' => getenv('DB_DATABASE'),
    'url' => getenv('DB_URL'),
    'cache' => getenv('APP_CONFIG_CACHE'),
    'hasEphemeralKey' => strlen(base64_decode(substr(getenv('APP_KEY'), 7), true)) === 32,
    'workers' => getenv('ROLLDOWN_WORKER_THREADS'),
]);
PHP);
    $process->setTimeout(30);
    $process->run();
    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
    $lines = explode("\n", trim($process->getOutput()));
    $result = json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR);
    expect($result)->toBe([
        'environment' => 'testing', 'driver' => 'sqlite', 'database' => ':memory:', 'url' => 'sqlite:///:memory:',
        'cache' => 'bootstrap/cache/config.testing.php', 'hasEphemeralKey' => true, 'workers' => '1',
    ]);
    expect(getenv('APP_ENV'))->toBe($parentEnvironment);
    foreach ($before as $path => $hash) {
        expect(is_file($path) ? hash_file('sha256', $path) : null)->toBe($hash);
    }
});
