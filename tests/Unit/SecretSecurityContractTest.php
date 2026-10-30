<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

test('secret checks preserve pinned downloads isolated commands and the existing quality contract', function () {
    $root = dirname(__DIR__, 2);
    $composer = json_decode(file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    expect($composer['scripts']['secret:check'])->toBe(['@secret:tree', '@secret:history', '@secret:test']);
    expect($composer['scripts']['secret:install'])->toBe(['python -B tests/Support/secret_security.py install']);
    foreach (['tree', 'history'] as $operation) {
        expect($composer['scripts']['secret:'.$operation])->toBe([
            '@ci:environment', 'python -B tests/Support/secret_security.py '.$operation,
        ]);
    }
    expect($composer['scripts']['secret:test'])->toBe(['@ci:environment', 'python -B tests/SecretSecurityTest.py']);
    $lock = json_decode(file_get_contents($root.'/gitleaks.lock.json'), true, flags: JSON_THROW_ON_ERROR);
    expect($lock['version'])->toBe('8.30.1');
    foreach (['rulesSha256', 'normalizedRulesSha256'] as $key) {
        expect($lock[$key])->toMatch('/^[a-f0-9]{64}$/');
    }
    expect(array_keys($lock['platforms']))->toEqualCanonicalizing(['windows-x64', 'linux-x64']);
    foreach ($lock['platforms'] as $pin) {
        expect($pin['asset'])->toStartWith('gitleaks_8.30.1_');
        foreach (['sha256', 'binarySha256'] as $key) {
            expect($pin[$key])->toMatch('/^[a-f0-9]{64}$/');
        }
    }
});

test('secret security requires complete history without credential persistence uploads or conditional bypasses', function () {
    $workflow = Yaml::parseFile(dirname(__DIR__, 2).'/.github/workflows/tests.yml');
    $job = $workflow['jobs']['secret-security'];
    expect($workflow['jobs']['ci']['needs'])->toContain('secret-security');
    expect($job)->not->toHaveKeys(['if', 'continue-on-error', 'permissions']);
    expect($job['timeout-minutes'])->toBe(15);
    $checkout = $job['steps'][0];
    expect($checkout['uses'])->toStartWith('actions/checkout@');
    expect($checkout['with'])->toBe(['persist-credentials' => false, 'fetch-depth' => 0]);
    expect(array_column($job['steps'], 'run'))->toContain('composer secret:install', 'composer secret:check', 'git diff --exit-code HEAD');
    foreach ($job['steps'] as $step) {
        expect($step)->not->toHaveKey('continue-on-error');
        if (($step['run'] ?? null) === 'composer secret:check') {
            expect($step)->not->toHaveKey('if');
        }
    }
});

test('every workflow keeps untrusted code on hosted read only jobs with pinned actions and safe shell boundaries', function () {
    $paths = array_merge(glob(dirname(__DIR__, 2).'/.github/workflows/*.yml'), glob(dirname(__DIR__, 2).'/.github/workflows/*.yaml'));
    expect($paths)->not->toBeEmpty();
    foreach ($paths as $path) {
        $workflow = Yaml::parseFile($path);
        expect($workflow['permissions'])->toBe(['contents' => 'read']);
        expect(array_keys($workflow['on']))->toEqualCanonicalizing(['push', 'pull_request']);
        foreach ($workflow['jobs'] as $name => $job) {
            expect($job['runs-on'])->toBe('ubuntu-latest');
            expect($job)->not->toHaveKeys(['permissions', 'secrets', 'continue-on-error']);
            if (basename($path) === 'tests.yml' && in_array($name, ['postgres-integration', 'financial-e2e'], true)) {
                expect(array_keys($job['services']))->toBe(['postgres']);
                expect($job['services']['postgres']['image'])->toBe('postgres:18.6-bookworm@sha256:5c4783a8cc3f0074912164f94dfa1be4e043438f0f707cf968d7af9a0d31a2c8');
                expect($job['services']['postgres'])->not->toHaveKey('volumes');
                expect($job['services']['postgres']['env'])->toBe([
                    'POSTGRES_DB' => 'intelledger_ci_bootstrap', 'POSTGRES_USER' => 'intelledger_ci', 'POSTGRES_PASSWORD' => 'ci-only',
                ]);
            } else {
                expect($job)->not->toHaveKey('services');
            }
            foreach ($job['steps'] as $step) {
                expect($step)->not->toHaveKey('continue-on-error');
                if (isset($step['run'])) {
                    expect($step['run'])->not->toContain('${{');
                }
                if (isset($step['uses'])) {
                    expect($step['uses'])->toMatch('/@[a-f0-9]{40}$/');
                    expect($step['uses'])->not->toMatch('/actions\/(?:upload-artifact|download-artifact|cache)@/');
                    if (str_starts_with($step['uses'], 'actions/checkout@')) {
                        expect($step['with']['persist-credentials'])->toBeFalse();
                        expect($step['with'])->not->toHaveKeys(['token', 'ssh-key', 'ref', 'repository']);
                    }
                    if (isset($step['with']['cache'])) {
                        expect($step['uses'])->toStartWith('actions/setup-node@');
                        expect($step['with']['cache'])->toBe('npm');
                    }
                }
            }
        }
        expect(file_get_contents($path))->not->toContain('${{ secrets.', 'id-token: write', 'security-events: write');
    }
});
