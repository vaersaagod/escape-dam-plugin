<?php

namespace escape\escapedam\services;

use Craft;
use craft\base\Component;
use craft\base\VolumeInterface;
use craft\db\Query;
use craft\elements\Asset;
use craft\helpers\Assets as AssetsHelper;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\models\Site;
use craft\models\VolumeFolder;

use escape\escapedam\EscapeDam;
use escape\escapedam\fields\EscapeDamField;
use escape\escapedam\helpers\FileHelper;
use escape\escapedam\models\Settings;
use escape\escapedam\records\ImportedFile as ImportedFileRecord;

use yii\web\BadRequestHttpException;

class Files extends Component
{

    /** @var \string[][] */
    const LANGUAGE_CODE_MAP = [
        'nb' => ['nb-NO', 'nn', 'nn-NO', 'no'],
    ];

    /**
     * @param int $fieldId
     * @param int|null $elementId
     * @return VolumeFolder|null
     * @throws \Exception
     */
    public function getFolderForImportByFieldAndElement(int $fieldId, int $elementId = null)
    {
        /** @var EscapeDamField $field */
        $field = Craft::$app->getFields()->getFieldById((int)$fieldId);
        if (!($field instanceof EscapeDamField)) {
            throw new \Exception('The field provided is not an Escape DAM field');
        }
        $element = $elementId ? Craft::$app->getElements()->getElementById((int)$elementId) : null;
        $folderId = $field->resolveDynamicPathToImportFolderId($element);
        if (!$folderId) {
            return null;
        }
        return Craft::$app->getAssets()->findFolder(['id' => (int)$folderId]);
    }

    /**
     * Import a file to a field as Asett
     *
     * @param int $fileId
     * @param int $fieldId
     * @param int|null $elementId
     * @param int|null $siteId
     * @param int|null $folderId
     * @return Asset
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \craft\errors\SiteNotFoundException
     * @throws \yii\base\Exception
     * @throws \yii\db\Exception
     */
    public function importFile(int $fileId, int $fieldId, int $elementId = null, int $siteId = null, int $folderId = null): Asset
    {

        // Has the file already been imported?
        $asset = $this->getImportedAsset($fileId, $fieldId, $elementId);

        if ($asset) {
            return $asset;
        }

        $assets = Craft::$app->getAssets();

        // Get the upload location from the field settings
        /** @var VolumeFolder $folder */
        if ($folderId) {
            $folder = $assets->findFolder(['id' => $folderId]);
        } else {
            $folder = $this->getFolderForImportByFieldAndElement($fieldId, $elementId);
        }

        if (!$folder) {
            throw new \Exception('The target folder provided for importing is not valid');
        }

        /** @var VolumeInterface $volume */
        $volume = $folder->getVolume();

        // Get file data from the Escape DAM API
        $fileData = EscapeDam::getInstance()->api->getFileDetailsById($fileId);
        if (!$fileData) {
            throw new \Exception("Could not retrieve details and metadata for remote file {$fileId}");
        }

        // Download the original file
        $tempPath = AssetsHelper::tempFilePath($fileData['extension']);
        FileHelper::downloadFile($fileData['imageUrl'] ?? $fileData['url'], $tempPath);

        // Get the default site – we'll create the asset here first
        $sitesService = Craft::$app->getSites();
        /** @var Site $defaultSite */
        $defaultSite = $siteId ? $sitesService->getSiteById($siteId) : $sitesService->getCurrentSite();
        $defaultSiteLocalizedData = $this->_getLocalizedDataForSite($defaultSite, $fileData['localizedData']) ?? $fileData['localizedData']['en'];

        $filename = AssetsHelper::prepareAssetName($defaultSiteLocalizedData['filename']);

        $asset = new Asset();
        $asset->tempFilePath = $tempPath;
        $asset->filename = $filename;
        $asset->newFolderId = $folder->id;
        $asset->volumeId = $folder->volumeId;
        $asset->avoidFilenameConflicts = true;
        $asset->setScenario(Asset::SCENARIO_CREATE);

        $this->_populateImportedAssetFieldValues($asset, $defaultSiteLocalizedData['fieldValues'] ?? []);

        if (!Craft::$app->getElements()->saveElement($asset)) {
            throw new \Exception('Unable to save imported Asset');
        }

        // Create a ImportedFileRecord to keep track of this Asset
        $importedFileRecord = new ImportedFileRecord();
        $importedFileRecord->assetId = (int)$asset->id;
        $importedFileRecord->damId = $fileId;
        $importedFileRecord->fieldId = $fieldId;
        $importedFileRecord->sourceElementId = $elementId;
        $importedFileRecord->settings = Json::encode($fileData);
        $importedFileRecord->dateSynced = Db::prepareDateForDb(new \DateTime());

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {

            if (!$importedFileRecord->save()) {
                throw new \Exception("Could not save imported file record for Asset {$asset->id}.");
            }

            $transaction->commit();

        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        // Propagate Asset metadata to other sites
        $sites = Craft::$app->getSites()->getAllSites();

        /** @var Site $site */
        foreach ($sites as $site) {
            if ((int)$site->id === (int)$defaultSite->id) {
                continue;
            }
            $localizedData = $this->_getLocalizedDataForSite($site, $fileData['localizedData']);
            if (!$localizedData) {
                continue;
            }
            $siteAsset = Craft::$app->getAssets()->getAssetById($asset->id, $site->id);
            if (!$siteAsset) {
                throw new \Exception("Could not get Asset {$asset->id} for site {$site->id}");
            }
            $this->_populateImportedAssetFieldValues($siteAsset, $localizedData['fieldValues'] ?? []);
            Craft::$app->getElements()->saveElement($siteAsset, false, false);
        }

        return $asset;

    }

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

        if (!$assetId) {
            return null;
        }

        return Craft::$app->getAssets()->getAssetById($assetId);
    }

    /**
     * Return the original DAM file data for an imported Asset
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

    /**
     * Grabs localized data from raw data array, using the local LANGUAGE_CODE_MAP constant to map different language codes to the same DAM language
     *
     * @param Site $site
     * @param $localizedData
     * @return mixed|null
     */
    private function _getLocalizedDataForSite(Site $site, $localizedData)
    {
        $language = $site->language;
        if (\in_array($language, \array_keys($localizedData))) {
            return $localizedData[$language];
        }
        foreach (self::LANGUAGE_CODE_MAP as $languageCode => $languages) {
            if (\in_array($language, $languages)) {
                return $localizedData[$languageCode] ?? null;
            }
        }
        return null;
    }

    /**
     * @param Asset $asset
     * @param array $data
     */
    private function _populateImportedAssetFieldValues(Asset &$asset, array $data)
    {
        /** @var Settings $settings */
        $settings = EscapeDam::getInstance()->getSettings();
        $metaDataFieldMap = $settings->metaDataFieldMap ?: null;
        if (!$metaDataFieldMap || !\is_array($metaDataFieldMap) || empty($metaDataFieldMap)) {
            return;
        }
        $fieldValues = [];
        foreach ($metaDataFieldMap as $attribute => $fieldHandle) {
            $fieldValues[$fieldHandle] = $data[$attribute] ?? null;
        }
        $asset->setFieldValues($fieldValues);
    }
}
