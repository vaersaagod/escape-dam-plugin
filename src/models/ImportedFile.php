<?php


namespace escape\escapedam\models;


use craft\base\Model;

class ImportedFile extends Model
{
    /**
     * @var int|null
     */
    public $id;

    /**
     * @var int|null
     */
    public $damId;

    /**
     * @var int|null
     */
    public $assetId;

    /**
     * @var array|null
     */
    public $settings;

}
