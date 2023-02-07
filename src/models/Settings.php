<?php

namespace escape\escapedam\models;

use craft\base\Model;

class Settings extends Model
{

    /** @var string|null */
    public ?string $damUrl = null;

    /** @var string|null */
    public ?string $jwtSecret = null;

    /** @var string|null */
    public ?array $metaDataFieldMap;

    /** @var float|int */
    public float|int $hlsVideoLazyloadDelay = 0;

    /** @var string */
    public string $hlsVideoLazyloadEvent = 'DOMContentLoaded';

    /** @var string|null */
    public ?string $pluginName = null;

    /** @var string|null */
    public ?string $cpSectionPath = null;

    /** @var string[]|null */
    public ?array $allowedVolumesForImport = null;
}
