<?php

namespace escape\escapedam\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\elements\Asset;
use craft\helpers\Json;

class Files extends Component
{

    /**
     * @param int $damId
     * @param int $fieldId
     * @param int|null $elementId
     * @return Asset|null
     */
    public function getImportedAsset(int $damId, int $fieldId, $elementId = null)
    {
        $assetId = (int)(new Query())
            ->select(['assets.id'])
            ->from('{{%assets}} AS assets')
            ->innerJoin('{{%escapedam_importedfiles}} AS importedfiles', 'importedfiles.assetId=assets.id')
            ->innerJoin('{{%elements}} AS elements', 'elements.id=assets.id')
            ->where('importedfiles.damId=:damId', [':damId' => $damId])
            ->andWhere('importedfiles.fieldId=:fieldId', [':fieldId' => $fieldId])
            ->andWhere('importedfiles.sourceElementId=:elementId', [':elementId' => $elementId])
            ->andWhere('elements.dateDeleted IS NULL') // Make sure we don't include soft deleted Assets
            ->scalar();

        return $assetId ? Craft::$app->getAssets()->getAssetById($assetId) : null;
    }

    /**
     * Return the original DAM ID for an imported Asset
     *
     * @param Asset $asset
     * @return mixed|null
     */
    public function getFileForImportedAsset(Asset $asset)
    {
        $damFileSettings = (new Query())
            ->select(['importedfiles.settings'])
            ->from('{{%escapedam_importedfiles}} AS importedfiles')
            ->where('importedfiles.assetId=:assetId', [':assetId' => (int)$asset->id])
            ->scalar();
        if (!$damFileSettings || !Json::isJsonObject($damFileSettings)) {
            return null;
        }
        return Json::decode($damFileSettings);
    }

    /**
     * @param int|int[] $assetId
     * @param int $fieldId
     * @param int $elementId
     */
    public function relateImportedAssetToElement($assetId, int $fieldId, int $elementId)
    {
        Craft::$app->getDb()->createCommand()->update('{{%escapedam_importedfiles}}', [
            'sourceElementId' => $elementId,
        ], [
            'assetId' => $assetId,
            'fieldId' => $fieldId,
            'sourceElementId' => null,
        ])->execute();
    }
}
