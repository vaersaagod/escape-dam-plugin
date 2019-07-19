<?php
namespace escape\escapedam\controllers;

use craft\models\Site;
use craft\models\VolumeFolder;
use escape\escapedam\EscapeDam;
use escape\escapedam\fields\EscapeDamField;
use escape\escapedam\helpers\FileHelper;

use Craft;
use craft\db\Query;
use craft\elements\Asset;
use craft\helpers\Assets as AssetsHelper;
use craft\web\Controller;

use escape\escapedam\models\Settings;
use yii\web\BadRequestHttpException;
use yii\web\Response;

class FilesController extends Controller
{

    const LANGUAGE_CODE_MAP = [
        'nb' => ['nb-NO', 'nn', 'nn-NO', 'no'],
    ];

    public function actionImportFile(): Response
    {

//        $this->requireCpRequest();
//        $this->requirePostRequest();
//        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();

        $fileId = $request->getRequiredParam('fileId');
        $folderId = (int)$request->getParam('folderId');
        $fieldId = (int)$request->getParam('fieldId');
        $siteId = (int)$request->getParam('siteId');
        $elementId = (int)$request->getParam('elementId');

        if (empty($folderId) && (empty($fieldId) || empty($elementId))) {
            throw new BadRequestHttpException('No target destination provided for importing');
        }

        try {

            // Get the upload location from the field settings
            if (empty($folderId)) {
                /** @var EscapeDamField $field */
                $field = Craft::$app->getFields()->getFieldById((int)$fieldId);
                if (!($field instanceof EscapeDamField)) {
                    throw new BadRequestHttpException('The field provided is not an Escape DAM field');
                }
                $element = $elementId ? Craft::$app->getElements()->getElementById((int)$elementId) : null;
                $folderId = $field->resolveDynamicPathToImportFolderId($element);
            }

            if (empty($folderId)) {
                throw new BadRequestHttpException('The target destination provided for importing is not valid');
            }

            $assets = Craft::$app->getAssets();

            /** @var VolumeFolder $folder */
            $folder = $assets->findFolder(['id' => $folderId]);

            if (!$folder) {
                throw new BadRequestHttpException('The target folder provided for importing is not valid');
            }

            // Get file data from the Escape DAM API
            $fileData = EscapeDam::$plugin->api->getFileDetailsById($fileId);
            if (!$fileData) {
                throw new \Exception("Could not retrieve details and metadata for remote file {$fileId}");
            }

            // Download the original file
            $tempPath = AssetsHelper::tempFilePath($fileData['extension']);
            FileHelper::downloadFile($fileData['url'], $tempPath);

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
                // In case of error, let user know about it.
                $errors = $asset->getFirstErrors();
                return $this->asErrorJson(Craft::t('app', 'Failed to save the Asset:') . implode(";\n", $errors));
            }
            
            // Propagate metadata to other sites
            $sites = Craft::$app->getSites()->getAllSites();
            /** @var Site $site */
            foreach ($sites as $site) {
                if ((int)$site->id === (int)$defaultSite->id) {
                    continue;
                }
                $language = $site->language;
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

            return $this->asJson([
                'success' => true,
                'filename' => $asset->filename,
                'assetId' => $asset->id
            ]);

        } catch (\Throwable $e) {
            Craft::error('An error occurred when importing a DAM file: ' . $e->getMessage(), __METHOD__);
            Craft::$app->getErrorHandler()->logException($e);
            return $this->asErrorJson($e->getMessage());
        }
        
    }

    /**
     * Grabs localized data from raw data array, using the local LANGUAGE_CODE_MAP constant to map different language codes to the same DAM language
     *
     * @param Site $site
     * @param $localizedData
     * @return |null
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
        $settings = EscapeDam::$plugin->getSettings();
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

    /**
     * Require an Assets permissions.
     *
     * @param string $permissionName Name of the permission to require.
     * @param VolumeFolder $folder Folder on the Volume on which to require the permission.
     */
    private function _requirePermissionByFolder(string $permissionName, VolumeFolder $folder)
    {
        if (!$folder->volumeId) {
            $userTemporaryFolder = Craft::$app->getAssets()->getUserTemporaryUploadFolder();
            // Skip permission check only if it's the user's temporary folder
            if ($userTemporaryFolder->id == $folder->id) {
                return;
            }
        }
        /** @var Volume $volume */
        $volume = $folder->getVolume();
        $this->_requirePermissionByVolumeId($permissionName, $volume->uid);
    }

    /**
     * Require an Assets permissions.
     *
     * @param string $permissionName Name of the permission to require.
     * @param string $volumeUid The volume uid on which to require the permission.
     */
    private function _requirePermissionByVolumeId(string $permissionName, string $volumeUid)
    {
        $this->requirePermission($permissionName . ':' . $volumeUid);
    }


}
