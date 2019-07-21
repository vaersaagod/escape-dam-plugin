<?php

namespace escape\escapedam\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\elements\Asset;

class Files extends Component
{

    /**
     * @param int $damId
     * @return Asset|null
     */
    public function getImportedAssetByDamFileId(int $damId)
    {
        $assetId = (int)(new Query())
            ->select(['assets.id'])
            ->from('{{%assets}} AS assets')
            ->innerJoin('{{%escapedam_importedfiles}} AS importedfiles', 'importedfiles.assetId=assets.id')
            ->innerJoin('{{%elements}} AS elements', 'elements.id=assets.id')
            ->where('importedfiles.damId=:damId', [':damId' => $damId])
            ->andWhere('elements.dateDeleted IS NULL') // Make sure we don't include soft deleted Assets
            ->scalar();

        /** @var Asset $asset */
        return $assetId ? Craft::$app->getAssets()->getAssetById($assetId) : null;
    }
}
