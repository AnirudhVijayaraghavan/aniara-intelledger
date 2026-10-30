<?php

declare(strict_types=1);

use Tests\Support\ArchitectureRules;

arch('data objects cannot depend on HTTP presentation or Eloquent persistence', function () {
    ArchitectureRules::assertDtoBoundaries('App\Data');
});

arch('financial actions cannot depend on controllers form requests or Inertia', function () {
    ArchitectureRules::assertActionBoundaries('App\Actions\Finance');
});

arch('models and policies cannot depend on controllers', function () {
    ArchitectureRules::assertNoControllers(['App\Models', 'App\Policies']);
});

arch('application classes cannot call debugging termination evaluation or direct process functions', function () {
    ArchitectureRules::assertNoUnsafeCalls('App');
});

arch('application source cannot execute shell expressions', function () {
    $directory = dirname(__DIR__, 2).'/app';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->getExtension() === 'php') {
            ArchitectureRules::assertNoShellExpressions(file_get_contents($file->getPathname()), $file->getPathname());
        }
    }
});

arch('financial core code avoids explicit floats and native division', function () {
    $root = dirname(__DIR__, 2);
    foreach (['Actions/Finance', 'Data', 'Enums', 'Models', 'Concerns', 'Rules'] as $area) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app/'.$area, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                ArchitectureRules::assertNoNativeFloats(file_get_contents($file->getPathname()), $file->getPathname());
            }
        }
    }
});
