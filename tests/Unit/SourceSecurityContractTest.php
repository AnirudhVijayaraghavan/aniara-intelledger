<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

test('source security uses pinned local rules and separate check only commands', function () {
    $root = dirname(__DIR__, 2);
    $composer = json_decode(file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    expect($composer['scripts']['source:check'])->toBe(['@source:scan', '@source:test']);
    foreach (['scan', 'test'] as $operation) {
        expect($composer['scripts']['source:'.$operation])->toBe([
            '@ci:environment', '@php -d memory_limit=64M tests/Support/source-security.php '.$operation,
        ]);
    }
    $requirements = file_get_contents($root.'/semgrep-requirements.txt');
    expect($requirements)->toContain('semgrep==1.182.1', 'pywin32==311; sys_platform == "win32"');
    foreach (explode("\n", trim($requirements)) as $requirement) {
        if (! str_starts_with($requirement, '#')) {
            expect(trim($requirement))->toMatch('/^[A-Za-z0-9_.-]+==[A-Za-z0-9.]+(?:; sys_platform == "win32")?$/');
        }
    }
    $rules = Yaml::parseFile($root.'/semgrep.yml')['rules'];
    expect($rules)->toHaveCount(15);
    expect(array_unique(array_column($rules, 'id')))->toHaveCount(15);
    foreach ($rules as $rule) {
        expect($rule['severity'])->toBe('ERROR');
        expect($rule)->not->toHaveKeys(['fix', 'fix-regex']);
        expect($rule['id'])->toMatch('/^il\.[a-z.-]+$/');
    }
});

test('source security is a mandatory read only job with pinned tooling and no source uploads', function () {
    $workflow = Yaml::parseFile(dirname(__DIR__, 2).'/.github/workflows/tests.yml');
    $job = $workflow['jobs']['source-security'];
    expect($workflow['jobs']['ci']['needs'])->toContain('source-security');
    expect($workflow['permissions'])->toBe(['contents' => 'read']);
    expect($job)->not->toHaveKeys(['if', 'continue-on-error', 'permissions']);
    expect($job['timeout-minutes'])->toBe(15);
    $commands = implode("\n", array_column($job['steps'], 'run'));
    expect($commands)->toContain(
        'composer source:check', 'python -m venv .phpunit.cache/semgrep-venv',
        '-r semgrep-requirements.txt', 'pip check', 'git diff --exit-code HEAD',
    );
    expect($commands)->not->toMatch('/semgrep (?:ci|login)|--config(?:=| )(?:auto|p\/|https?:)|--(?:fix|autofix)|\|\| true/');
    foreach ($job['steps'] as $step) {
        expect($step)->not->toHaveKey('continue-on-error');
        if (isset($step['uses'])) {
            expect($step['uses'])->toMatch('/@[a-f0-9]{40}$/');
        }
        if (($step['run'] ?? null) === 'composer source:check') {
            expect($step)->not->toHaveKey('if');
        }
    }
});
