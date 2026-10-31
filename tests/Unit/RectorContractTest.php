<?php

declare(strict_types=1);

use Rector\Php80\Rector\FuncCall\ClassOnObjectRector;
use Rector\Php82\Rector\Encapsed\VariableInStringInterpolationFixerRector;
use Rector\Renaming\Rector\Name\RenameClassRector;
use Symfony\Component\Process\Process;

test('Rector loads only the reviewed modernization rules and disables its implicit rename rule', function () {
    $process = new Process([
        PHP_BINARY, '-d', 'memory_limit=512M', 'vendor/bin/rector', 'list-rules', '--config=rector.php', '--output-format=json',
    ], dirname(__DIR__, 2), ['PAO_DISABLE' => '1']);
    $process->setTimeout(60);
    $process->run();

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
    $rules = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    expect($rules['rectors'])->toEqualCanonicalizing([
        ClassOnObjectRector::class,
        VariableInStringInterpolationFixerRector::class,
        RenameClassRector::class,
    ]);
    expect($rules['skipped-rectors'])->toBe([RenameClassRector::class]);
});

test('the Composer Rector gate detects approved modernizations without rewriting source', function () {
    $root = dirname(__DIR__, 2);
    $directory = sys_get_temp_dir().'/intelledger-rector-'.bin2hex(random_bytes(8));
    mkdir($directory);
    $fixture = $directory.'/probe.php';
    try {
        $sources = [
            <<<'PHP'
<?php
function rectorDescription(object $value, string $label): string
{
    return get_class($value).": ${label}";
}
PHP,
            <<<'PHP'
<?php
function rectorDescription(object $value, string $label): string
{
    return $value::class.": {$label}";
}
PHP,
        ];
        foreach ($sources as $index => $source) {
            file_put_contents($fixture, $source);
            $before = hash_file('sha256', $fixture);
            $process = new Process([
                'composer', 'rector:check', '--', $fixture, '--output-format=json',
            ], $root, ['PAO_DISABLE' => '1']);
            $process->setTimeout(120);
            $process->run();

            expect($process->getExitCode())->toBe($index === 0 ? 2 : 0, $process->getOutput().$process->getErrorOutput());
            expect(hash_file('sha256', $fixture))->toBe($before);
            if ($index === 0) {
                expect($process->getOutput())->toContain('ClassOnObjectRector', 'VariableInStringInterpolationFixerRector');
            }
        }
    } finally {
        if (is_file($fixture)) {
            unlink($fixture);
        }
        rmdir($directory);
    }
});
