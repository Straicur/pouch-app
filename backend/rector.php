<?php

declare(strict_types = 1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\CodeQuality\Rector\Identical\SimplifyBoolIdenticalTrueRector;
use Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector;
use Rector\CodingStyle\Rector\Encapsed\EncapsedStringsToSprintfRector;
use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Php80\Rector\ClassConstFetch\ClassOnThisVariableObjectRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Strict\Rector\Empty_\DisallowedEmptyRuleFixerRector;

return RectorConfig::configure()
    ->withRootFiles()
    ->withPaths([
        __DIR__ . '/config',
        __DIR__ . '/public',
        __DIR__ . '/src'
    ])
    ->withSkip([
        __DIR__ . '/config/secrets',
        // entities
        ClassPropertyAssignToConstructorPromotionRector::class => [
            __DIR__ . '/src/Entity'
        ],
        // code quality
        FlipTypeControlToUseExclusiveTypeRector::class,
        SimplifyBoolIdenticalTrueRector::class,
        // coding style
        CatchExceptionNameMatchingTypeRector::class,
        EncapsedStringsToSprintfRector::class,
        InlineConstructorDefaultToPropertyRector::class,
        ClassOnThisVariableObjectRector::class,
        // strict booleans
        DisallowedEmptyRuleFixerRector::class
    ])
     ->withPhpSets(php84: true)
     ->withAttributesSets()
     ->withPreparedSets(
         deadCode: true,
         codeQuality: true,
         codingStyle: true,
         typeDeclarations: true,
         earlyReturn: true,
         symfonyCodeQuality:true,
         symfonyConfigs: true
     )
    ->withImportNames()
    ->withSets([
        LevelSetList::UP_TO_PHP_84,
    ])
    ->withRules([])
    ->withCache(
        cacheDirectory: __DIR__ . '/var/build/rector',
        cacheClass: FileCacheStorage::class
    )
    ->withParallel(
        timeoutSeconds: 300,
        maxNumberOfProcess: 4,
        jobSize: 32
    );
