<?php

declare(strict_types=1);

use PhpParser\Error;
use PHPUnit\Framework\ExpectationFailedException;
use Symfony\Component\Process\Process;
use Tests\Support\ArchitectureRules;

test('native architecture rules reject forbidden dependencies in isolated probes and retain legitimate integrations', function () {
    $root = dirname(__DIR__, 2);
    $directory = sys_get_temp_dir().'/intelledger-architecture-'.bin2hex(random_bytes(8));
    mkdir($directory);
    mkdir($directory.'/classes');
    $fixtures = [
        'DtoController' => 'public function dependency(): string { return \\App\\Http\\Controllers\\Finance\\FinancialAccountController::class; }',
        'DtoRequest' => 'public function dependency(): string { return \\App\\Http\\Requests\\Finance\\SaveFinancialAccountRequest::class; }',
        'DtoFormRequest' => 'public function dependency(): string { return \\Illuminate\\Foundation\\Http\\FormRequest::class; }',
        'DtoModel' => 'public function dependency(): string { return \\App\\Models\\FinancialAccount::class; }',
        'DtoEloquent' => 'public function dependency(): string { return \\Illuminate\\Database\\Eloquent\\Model::class; }',
        'DtoDatabase' => 'public function dependency(): string { return \\Illuminate\\Support\\Facades\\DB::class; }',
        'ActionController' => 'public function dependency(): string { return \\App\\Http\\Controllers\\Finance\\FinancialAccountController::class; }',
        'ActionRequest' => 'public function dependency(): string { return \\App\\Http\\Requests\\Finance\\SaveFinancialAccountRequest::class; }',
        'ActionInertia' => 'public function dependency(): string { return \\Inertia\\Inertia::class; }',
        'ModelController' => 'public function dependency(): string { return \\App\\Http\\Controllers\\Finance\\FinancialAccountController::class; }',
        'PolicyController' => 'public function dependency(): string { return \\App\\Http\\Controllers\\Finance\\FinancialAccountController::class; }',
        'DebugCall' => 'public function forbidden(): void { dd("probe"); }',
        'EvalCall' => 'public function forbidden(): void { eval("1;"); }',
        'ProcessCall' => 'public function forbidden(): void { \\exec("probe"); }',
        'ExitCall' => 'public function forbidden(): void { exit; }',
        'AllowedDto' => 'public function value(\\App\\Enums\\FinancialAccountType $type): string { return (string) \\Brick\\Math\\BigDecimal::of("0.00000001"); }',
        'AllowedAction' => 'public function upload(\\Illuminate\\Http\\UploadedFile $file): string { return \\App\\Models\\FinancialAccount::class; }',
        'NoStrict' => '',
    ];
    try {
        foreach ($fixtures as $name => $body) {
            $declaration = $name === 'NoStrict' ? '' : 'declare(strict_types=1);';
            file_put_contents($directory.'/classes/'.$name.'.php', '<?php '.$declaration.' namespace ArchitectureProbe; final class '.$name.' { '.$body.' }');
        }
        file_put_contents($directory.'/ArchitectureProbeTest.php', <<<'PHP'
<?php
declare(strict_types=1);
use PHPUnit\Framework\ExpectationFailedException;
use Tests\Support\ArchitectureRules;
uses(\Pest\Arch\Concerns\Architectable::class);
$loader = require getcwd().'/vendor/autoload.php';
$loader->addPsr4('ArchitectureProbe\\', __DIR__.'/classes');

test('DTO boundaries reject each forbidden dependency', function (string $name, string $dependency) {
    expect(fn () => ArchitectureRules::assertDtoBoundaries('ArchitectureProbe\\'.$name))
        ->toThrow(ExpectationFailedException::class, $dependency);
})->with([
    ['DtoController', 'App\\Http\\Controllers'], ['DtoRequest', 'App\\Http\\Requests'],
    ['DtoFormRequest', 'Illuminate\\Foundation\\Http\\FormRequest'], ['DtoModel', 'App\\Models'],
    ['DtoEloquent', 'Illuminate\\Database'], ['DtoDatabase', 'Illuminate\\Support\\Facades\\DB'],
]);
test('Action boundaries reject presentation', function (string $name, string $dependency) {
    expect(fn () => ArchitectureRules::assertActionBoundaries('ArchitectureProbe\\'.$name))
        ->toThrow(ExpectationFailedException::class, $dependency);
})->with([
    ['ActionController', 'App\\Http\\Controllers'], ['ActionRequest', 'App\\Http\\Requests'], ['ActionInertia', 'Inertia'],
]);
test('model and policy boundaries reject controllers', function (string $name) {
    expect(fn () => ArchitectureRules::assertNoControllers('ArchitectureProbe\\'.$name))
        ->toThrow(ExpectationFailedException::class, 'App\\Http\\Controllers');
})->with(['ModelController', 'PolicyController']);
test('unsafe code is rejected without executing it', function (string $name, string $function) {
    expect(fn () => ArchitectureRules::assertNoUnsafeCalls('ArchitectureProbe\\'.$name))
        ->toThrow(ExpectationFailedException::class, $function);
})->with([['DebugCall', 'dd'], ['EvalCall', 'eval'], ['ProcessCall', 'exec'], ['ExitCall', 'exit']]);
test('a missing strict declaration fails the existing native expectation', function () {
    expect(fn () => expect('ArchitectureProbe\\NoStrict')->toUseStrictTypes())
        ->toThrow(\Pest\Arch\Exceptions\ArchExpectationFailedException::class, 'to use strict types');
});
test('legitimate enums decimal math uploads and Action persistence remain allowed', function () {
    ArchitectureRules::assertDtoBoundaries('ArchitectureProbe\\AllowedDto');
    ArchitectureRules::assertActionBoundaries('ArchitectureProbe\\AllowedAction');
});
PHP);
        $bootstrap = <<<'PHP'
require 'vendor/autoload.php';
Tests\Support\QualityEnvironment::configure();
$probe = new Symfony\Component\Process\Process([
    PHP_BINARY, '-d', 'memory_limit=256M', 'vendor/bin/pest', '--configuration=phpunit.xml',
    $argv[1].'/ArchitectureProbeTest.php',
    '--colors=never', '--compact', '--log-junit='.$argv[1].'/results.xml',
]);
$probe->setTimeout(60);
$probe->run();
echo $probe->getOutput();
fwrite(STDERR, $probe->getErrorOutput());
exit($probe->getExitCode() ?? 1);
PHP;
        $process = new Process([PHP_BINARY, '-r', $bootstrap, $directory], $root, ['PAO_DISABLE' => '1']);
        $process->setTimeout(75);
        $process->run();

        expect($process->getExitCode())->toBe(0, $process->getErrorOutput().$process->getOutput());
        $results = new DOMDocument;
        expect($results->load($directory.'/results.xml'))->toBeTrue();
        expect($results->getElementsByTagName('testcase')->length)->toBe(17);
        expect($results->getElementsByTagName('failure')->length)->toBe(0);
        expect($results->getElementsByTagName('error')->length)->toBe(0);
    } finally {
        foreach (array_keys($fixtures) as $name) {
            if (is_file($directory.'/classes/'.$name.'.php')) {
                unlink($directory.'/classes/'.$name.'.php');
            }
        }
        foreach (['ArchitectureProbeTest.php', 'results.xml'] as $name) {
            if (is_file($directory.'/'.$name)) {
                unlink($directory.'/'.$name);
            }
        }
        rmdir($directory.'/classes');
        rmdir($directory);
    }
});

test('float guard rejects executable precision losing constructs using the PHP AST', function (string $source) {
    expect(fn () => ArchitectureRules::assertNoNativeFloats($source, 'isolated probe'))
        ->toThrow(ExpectationFailedException::class, 'isolated probe');
})->with([
    'float cast' => '<?php $value = (float) "0.1";',
    'double cast' => '<?php $value = (double) "0.1";',
    'float literal arithmetic' => '<?php $value = 0.1 + 0.2;',
    'scientific literal' => '<?php $value = 1e3;',
    'native division' => '<?php $value = $amount / 3;',
    'division assignment' => '<?php $value /= 3;',
    'float conversion' => '<?php $value = \\floatval("0.1");',
    'aliased conversion' => '<?php use function floatval as toNumber; $value = toNumber("0.1");',
    'floating division' => '<?php $value = fdiv(1, 3);',
]);

test('AST guards ignore strings comments integer bookkeeping and decimal library arithmetic', function () {
    $source = <<<'PHP'
<?php
// Documentation: (float) 0.1 + 0.2; floatval($amount); `echo ignored`.
$description = '(double) 0.1 / 3 and floatval(1)';
$counter = 1 + 2;
$size = intdiv(8, 2);
$value = \Brick\Math\BigDecimal::of('9999999999999999.12345678')->dividedBy('3', 8);
$integer = new class { public function floatval(): int { return 1; } };
$integer->floatval();
PHP;

    ArchitectureRules::assertNoNativeFloats($source, 'allowed probe');
    ArchitectureRules::assertNoShellExpressions($source, 'allowed probe');
});

test('shell expressions are rejected structurally without running a command', function () {
    expect(fn () => ArchitectureRules::assertNoShellExpressions('<?php $value = `echo probe`;', 'isolated probe'))
        ->toThrow(ExpectationFailedException::class, 'isolated probe');
});

test('unparseable financial source fails closed instead of passing the structural guard', function () {
    expect(fn () => ArchitectureRules::assertNoNativeFloats('<?php $value = ;', 'broken probe'))->toThrow(Error::class);
});
