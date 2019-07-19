<?php


namespace escape\escapedam\models;


use craft\base\Model;

class Settings extends Model
{

    /** @var string|null */
    public $damUrl;

    /** @var string|null */
    public $jwtSecret;

    /** @var array|null */
    public $metaDataFieldMap;
}
