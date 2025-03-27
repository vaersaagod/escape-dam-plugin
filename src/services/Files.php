<?php

namespace escape\escapedam\services;

use Craft;
use craft\base\Component;
use craft\base\LocalVolumeInterface;
use craft\db\Query;
use craft\elements\Asset;
use craft\helpers\Assets as AssetsHelper;
use craft\helpers\ConfigHelper;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\models\Site;
use craft\models\VolumeFolder;

use escape\escapedam\EscapeDam;
use escape\escapedam\fields\EscapeDamField;
use escape\escapedam\helpers\ApiHelper;
use escape\escapedam\helpers\FileHelper;
use escape\escapedam\models\Settings;
use escape\escapedam\records\ImportedFile as ImportedFileRecord;

class Files extends Component
{

    /**
     * @param int|null $elementId
     * @throws \Exception
     */
    public function getFolderForImportByFieldAndElement(int $fieldId, int $elementId = null): ?VolumeFolder
    {
        /** @var EscapeDamField $field */
        $field = Craft::$app->getFields()->getFieldById((int)$fieldId);
        if (!($field instanceof EscapeDamField)) {
            throw new \Exception('The field provided is not an Escape DAM field');
        }
        $element = $elementId ? Craft::$app->getElements()->getElementById((int)$elementId) : null;
        $folderId = $field->getImportFolderId($element);
        if ($folderId === 0) {
            return null;
        }
        return Craft::$app->getAssets()->findFolder(['id' => (int)$folderId]);
    }

    /**
     * Import a file to a field as Asett
     *
     * @param int|null $elementId
     * @param int|null $siteId
     * @param int|null $folderId
     * @param int|null $uploaderId
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \craft\errors\SiteNotFoundException
     * @throws \yii\base\Exception
     * @throws \yii\db\Exception
     */
    public function importFile(int $fileId, int $fieldId, int $elementId = null, int $siteId = null, int $folderId = null, int $uploaderId = null): Asset
    {

        // Get uploader (either via the $uploaderId param, or the currently logged in user
        if ($uploaderId) {
            $uploader = Craft::$app->getUsers()->getUserById($uploaderId);
            if (!$uploader) {
                throw new \Exception('Invalid uploader ID');
            }
        } else {
            $uploader = Craft::$app->getUser()->getIdentity();
            if (!$uploader) {
                throw new \Exception('User is not logged in');
            }
        }

        // Has the file already been imported?
        $asset = $this->getImportedAsset($fileId, $fieldId, $elementId);

        if ($asset !== null) {
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

        // Get file data from the Escape DAM API
        EscapeDam::getInstance()->api->setUser($uploader);
        $fileData = EscapeDam::getInstance()->api->getFileDetailsById($fileId);

        // What kind of file is this?
        $kind = $fileData['kind'] ?? null;
        $fileUrl = $fileData['assetUrl'] ?? null;
        if ($kind === Asset::KIND_IMAGE) {
            // Check the file size and dimensions; if it's too large we don't want to download the original
            $width = $fileData['width'] ?? 0;
            $height = $fileData['height'] ?? 0;
            $size = $fileData['size'] ?? 0;
            if ($width > 4000 || $height > 4000 || $size > ConfigHelper::sizeInBytes('8M')) {
                $fileUrl = $fileData['imageUrl'] ?? null;
            }
            // Download the image file
            $tempPath = AssetsHelper::tempFilePath($fileData['extension']);
            FileHelper::downloadFile($fileUrl, $tempPath);
        } elseif ($kind === Asset::KIND_VIDEO) {
            // Create a JSON file and save it to the temp path
            $tempPath = AssetsHelper::tempFilePath('json');
            \file_put_contents($tempPath, Json::encode($fileData));
        } else {
            throw new \Exception('Unsupported file kind');
        }

        if (!\file_exists($tempPath) || !\is_file($tempPath)) {
            throw new \Exception("Could not download file \"{$fileUrl}\"");
        }

        // Get the default site – we'll create the asset here first
        $sitesService = Craft::$app->getSites();
        /** @var Site $defaultSite */
        $defaultSite = $siteId ? $sitesService->getSiteById($siteId) : $sitesService->getCurrentSite();
        $defaultSiteLocalizedData = $this->_getLocalizedDataForSite($defaultSite, $fileData['localizedData']) ?? $fileData['localizedData']['en'];

        $rawFilename = $defaultSiteLocalizedData['filename'];
        if ($kind === Asset::KIND_VIDEO) {
            $rawFilename .= '.json';
        }
        $filename = AssetsHelper::prepareAssetName($rawFilename);

        $asset = new Asset();
        $asset->tempFilePath = $tempPath;
        $asset->filename = $filename;
        $asset->newFolderId = $folder->id;
        $asset->setVolumeId($folder->volumeId);
        $asset->avoidFilenameConflicts = true;
        $asset->uploaderId = $uploader->id;
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

    public function getImportedAsset(int $damId, int $fieldId, ?int $elementId = null): ?Asset
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

        if ($assetId === 0) {
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
    public function getFileForImportedAsset(Asset $asset): mixed
    {
        if ($asset->isFolder) {
            return null;
        }
        $damFileSettings = (new Query())
            ->select(['importedfiles.settings'])
            ->from('{{%escapedam_importedfiles}} AS importedfiles')
            ->where('importedfiles.assetId=:assetId', [':assetId' => (int)$asset->getId()])
            ->scalar();

        if (!$damFileSettings || !Json::isJsonObject($damFileSettings)) {
            return null;
        }

        return Json::decode($damFileSettings);
    }

    /**
     * @throws \yii\base\InvalidConfigException
     */
    public function isImportedAsset(Asset $asset): bool
    {
        if ($asset->isFolder) {
            return false;
        }
        $cacheKey = 'escapedam-is-imported-asset:' . $asset->uid . ($asset->dateUpdated?->getTimestamp() ?? $asset->dateCreated?->getTimestamp() ?? '');
        $cachedResult = Craft::$app->getCache()->get($cacheKey);
        if ($cachedResult === 'true') {
            return true;
        }
        $result = (new Query())
            ->from('{{%escapedam_importedfiles}} AS importedfiles')
            ->where('importedfiles.assetId=:assetId', [':assetId' => (int)$asset->getId()])
            ->exists();
        Craft::$app->getCache()->set($cacheKey, $result ? 'true' : 'false', ConfigHelper::durationInSeconds('P30D'));
        return $result;
    }

    /**
     * @param $assetIds
     * @param int $fieldId
     * @param int $elementId
     * @return void
     * @throws \yii\db\Exception
     */
    public function relateImportedAssetToElement($assetIds, int $fieldId, int $elementId): void
    {

        if (!\is_array($assetIds)) {
            $assetIds = [$assetIds];
        }

        foreach ($assetIds as $assetId) {

            $id = (int)(new Query())
                ->select(['id'])
                ->from('{{%escapedam_importedfiles}}')
                ->where(['is not', 'damId', null])
                ->andWhere('assetId=:assetId', [':assetId' => $assetId])
                ->andWhere(['or', 'fieldId=:fieldId', 'fieldId IS NULL'], [':fieldId' => $fieldId])
                ->andWhere(['or', 'sourceElementId=:sourceElementId', 'sourceElementId IS NULL'], [':sourceElementId' => $elementId])
                ->scalar();

            if ($id === 0) {
                continue;
            }

            Craft::$app->getDb()->createCommand()->update('{{%escapedam_importedfiles}}', [
                'sourceElementId' => $elementId,
                'fieldId' => $fieldId,
            ], [
                'id' => $id,
            ])->execute();

        }
    }

    /**
     * @param Asset $asset
     * @return string
     * @throws \yii\base\InvalidConfigException
     */
    public function getContents(Asset $asset): string
    {
        if ($asset->isFolder) {
            return '';
        }
        $cacheKey = 'escapedam-' . $asset->uid . '-contents';
        $cachedContents = Craft::$app->getCache()->get($cacheKey);
        if ($cachedContents) {
            return $cachedContents;
        }
        try {
            $contents = $asset->getContents();
        } catch (\Throwable $e) {
            Craft::error($e->getMessage(), __METHOD__);
            $contents = '';
        }
        if ($contents !== '' && $contents !== '0') {
            Craft::$app->getCache()->set($cacheKey, $contents, ConfigHelper::durationInSeconds('P30D'));
        }
        return $contents;
    }

    /**
     * Grabs localized data from raw data array, using the local LANGUAGE_CODE_MAP constant to map different language codes to the same DAM language
     *
     * @param Site $site
     * @param $localizedData
     * @return mixed
     */
    private function _getLocalizedDataForSite(Site $site, $localizedData): mixed
    {
        $language = $site->language;
        if (array_key_exists($language, $localizedData)) {
            return $localizedData[$language];
        }
        foreach (ApiHelper::LANGUAGE_CODE_MAP as $languageCode => $languages) {
            if (\in_array($language, $languages)) {
                return $localizedData[$languageCode] ?? null;
            }
        }
        return null;
    }

    /**
     * @param Asset $asset
     * @param array $data
     * @return void
     */
    private function _populateImportedAssetFieldValues(Asset &$asset, array $data): void
    {
        if ($asset->isFolder) {
            return;
        }
        $settings = EscapeDam::getInstance()->getSettings();
        $metaDataFieldMap = $settings->metaDataFieldMap ?: null;
        if (!\is_array($metaDataFieldMap) || empty($metaDataFieldMap)) {
            return;
        }
        $fieldValues = [];
        foreach ($metaDataFieldMap as $attribute => $fieldHandle) {
            $fieldValues[$fieldHandle] = $data[$attribute] ?? null;
        }
        $asset->setFieldValues($fieldValues);
    }
}
