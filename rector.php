<?php
declare(strict_types=1);

use craft\rector\SetList as CraftSetList;

use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->sets([
        CraftSetList::CRAFT_CMS_40,
        SetList::CODE_QUALITY,
        LevelSetList::UP_TO_PHP_81
    ]);
};
