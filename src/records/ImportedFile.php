<?php


namespace escape\escapedam\records;


use craft\db\ActiveRecord;

/**
 * Class ImportedFileRecord
 * @package escape\escapedam\records
 *
 * @property $id
 * @property $damId
 * @property $assetId
 * @property $fieldId
 * @property $sourceElementId
 * @property $settings
 * @property $dateSynced
 * @property $retroActive
 */
class ImportedFile extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%escapedam_importedfiles}}';
    }
}
