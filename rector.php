<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Identical\SimplifyBoolIdenticalTrueRector;
use Rector\CodeQuality\Rector\If_\SimplifyIfReturnBoolRector;
use Rector\Config\RectorConfig;
use Rector\Php70\Rector\Ternary\TernaryToNullCoalescingRector;
use Rector\Php80\Rector\Class_\StringableForToStringRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\ValueObject\PhpVersion;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__,
        __DIR__.'/admin',
        __DIR__.'/include',
        __DIR__.'/tasks',
        __DIR__.'/a',
        __DIR__.'/c',
        __DIR__.'/r',
        __DIR__.'/u',
        __DIR__.'/403',
        __DIR__.'/scripts',
    ]);

    $rectorConfig->skip([
        __DIR__.'/admin/adminer/*',
        __DIR__.'/vendor/*',
        __DIR__.'/cache/*',
        __DIR__.'/include/lib/mtcaptcha/*',
        __DIR__.'/include/lib/facebook/vendor/*',
        __DIR__.'/include/lib/facebook/composer.json',
        __DIR__.'/include/lib/facebook/composer.lock',
    ]);

    $rectorConfig->phpVersion(PhpVersion::PHP_85);

    $rectorConfig->importNames();
    $rectorConfig->importShortClasses(false);

    $rectorConfig->parallel();

    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_85,
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::DEAD_CODE,
        SetList::EARLY_RETURN,
        SetList::TYPE_DECLARATION,
        SetList::PRIVATIZATION,
        SetList::STRICT_BOOLEANS,
        SetList::NAMING,
    ]);

    $rectorConfig->rule(StringableForToStringRector::class);
    $rectorConfig->rule(SimplifyIfReturnBoolRector::class);
    $rectorConfig->rule(SimplifyBoolIdenticalTrueRector::class);
    $rectorConfig->rule(TernaryToNullCoalescingRector::class);

};
