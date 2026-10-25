<?php

use Illuminate\Support\Facades\File;

function withTemporaryVersion(string $version, Closure $assertions): void
{
    $path = tempnam(sys_get_temp_dir(), 'intelledger-version-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary version file.');
    }

    File::put($path, $version.PHP_EOL);
    config(['app.version_file' => $path]);

    try {
        $assertions($path);
    } finally {
        File::delete($path);
    }
}

test('version bumps increment the selected component and reset lower components', function (string $current, string $bump, string $expected) {
    withTemporaryVersion($current, function (string $path) use ($current, $bump, $expected): void {
        $this->artisan('app:version', ['bump' => $bump])
            ->expectsOutput("Version updated: {$current} -> {$expected}")
            ->assertSuccessful();

        expect(File::get($path))->toBe($expected.PHP_EOL);
    });
})->with([
    'major' => ['1.2.3', 'major', '2.0.0'],
    'minor' => ['1.2.3', 'minor', '1.3.0'],
    'patch' => ['1.2.3', 'patch', '1.2.4'],
    'multi digit patch' => ['1.2.9', 'patch', '1.2.10'],
    'large version' => ['1.2.99999999999999999999', 'patch', '1.2.100000000000000000000'],
]);

test('version command displays the current version without changing it', function () {
    withTemporaryVersion('1.2.3', function (string $path): void {
        $this->artisan('app:version')->expectsOutput('1.2.3')->assertSuccessful();

        expect(File::get($path))->toBe('1.2.3'.PHP_EOL);
    });
});

test('invalid bump arguments leave the version file unchanged', function () {
    withTemporaryVersion('1.2.3', function (string $path): void {
        $this->artisan('app:version', ['bump' => 'release'])
            ->expectsOutput('Choose major, minor, or patch.')->assertExitCode(2);

        expect(File::get($path))->toBe('1.2.3'.PHP_EOL);
    });
});

test('malformed version files are rejected without being overwritten', function (string $version) {
    withTemporaryVersion($version, function (string $path) use ($version): void {
        $this->artisan('app:version', ['bump' => 'patch'])
            ->expectsOutput('Version must use MAJOR.MINOR.PATCH without leading zeros.')->assertFailed();

        expect(File::get($path))->toBe($version.PHP_EOL);
    });
})->with(['1.2', 'v1.2.3', '1.02.3', '1.2.3-beta', '']);

test('a missing version file is not silently recreated', function () {
    withTemporaryVersion('1.2.3', function (string $path): void {
        File::delete($path);

        $this->artisan('app:version', ['bump' => 'patch'])
            ->expectsOutput('Version file is missing.')->assertFailed();

        expect(File::exists($path))->toBeFalse();
    });
});

test('the command works with cached configuration predating the version file setting', function () {
    $appConfiguration = config('app');
    unset($appConfiguration['version_file']);
    config(['app' => $appConfiguration]);

    $this->artisan('app:version')->expectsOutput(trim(File::get(base_path('VERSION'))))->assertSuccessful();
});
