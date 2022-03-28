<?php

namespace escape\escapedam\models;

use craft\base\Model;

class Settings extends Model
{

    /** @var string|null */
    public ?string $damUrl;

    /** @var string|null */
    public ?string $jwtSecret;

    /** @var array|null */
    public ?array $metaDataFieldMap;

    /** @var float */
    public float $hlsVideoLazyloadDelay = 0;
}
