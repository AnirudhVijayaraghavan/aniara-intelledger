<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php\PhpVersionResolver\ComposerJsonPhpVersionResolver;
use Rector\Php80\Rector\FuncCall\ClassOnObjectRector;
use Rector\Php82\Rector\Encapsed\VariableInStringInterpolationFixerRector;
use Rector\Renaming\Rector\Name\RenameClassRector;

return RectorConfig::configure()
    ->withPhpVersion(ComposerJsonPhpVersionResolver::resolveFromCwdOrFail())
    ->withPaths([
        __DIR__.'/app/Data',
        __DIR__.'/app/Enums',
    ])
    ->withRules([
        ClassOnObjectRector::class,
        VariableInStringInterpolationFixerRector::class,
    ])
    ->withSkip([
        RenameClassRector::class,
    ])
    ->withoutParallel()
    ->withCache(cacheDirectory: __DIR__.'/.phpunit.cache/rector');
