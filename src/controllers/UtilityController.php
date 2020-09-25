<?php


namespace escape\escapedam\controllers;

use Craft;
use craft\db\Query;
use craft\elements\Asset;
use craft\web\Controller;
use escape\escapedam\queue\jobs\FixMissingImportedFileJob;
use escape\escapedam\records\ImportedFile;
use yii\web\BadRequestHttpException;

class UtilityController extends Controller
{

    /**
     * @return \yii\web\Response|null
     * @throws BadRequestHttpException
     * @throws \craft\errors\MissingComponentException
     */
    public function actionFixMissingImportedFileRecords()
    {

        $this->requirePostRequest();
        $this->requireCpRequest();

        $volumeId = (int)Craft::$app->getRequest()->getRequiredBodyParam('volumeId');
        $volume = Craft::$app->getVolumes()->getVolumeById($volumeId);

        if (!$volume) {
            throw new BadRequestHttpException('Invalid volume ID');
        }

        $siteId = (int)Craft::$app->getRequest()->getRequiredBodyParam('siteId');
        $site = Craft::$app->getSites()->getSiteById($siteId);

        if (!$site) {
            throw new BadRequestHttpException('Invalid site ID');
        }

        // Get all Asset IDs in the volume
        $assetIds = \array_map('intval', Asset::find()
            ->volumeId($volume->id)
            ->ids());

        if (!$assetIds) {
            Craft::$app->getSession()->setError("No Assets found in volume");
            return null;
        }

        // Get all imported file asset IDs
        $importedFileAssetIds = \array_map('intval', (new Query())
            ->select(['assetId'])
            ->groupBy('assetId')
            ->from(ImportedFile::tableName())
            ->column());

        $missingAssetIds = \array_values(\array_diff($assetIds, $importedFileAssetIds));
        $countMissingAssetIds = \count($missingAssetIds);

        if (!$countMissingAssetIds) {
            Craft::$app->getSession()->setError("No missing imported records to fix");
            return null;
        }

        Craft::$app->getQueue()->push(new FixMissingImportedFileJob([
            'assetIds' => $missingAssetIds,
            'siteId' => $siteId,
            'userId' => Craft::$app->getUser()->getIdentity()->id,
        ]));

        Craft::$app->getSession()->setNotice("Attempting to fix {$countMissingAssetIds} missing imported file records in \"{$volume->name}\"");

        return $this->redirectToPostedUrl();
    }

    /**
     * @return \yii\web\Response
     * @throws BadRequestHttpException
     * @throws \craft\errors\MissingComponentException
     * @throws \yii\db\Exception
     */
    public function actionDeleteAllRecordsCreatedByUtility()
    {
        $affectedRows = Craft::$app->getDb()->createCommand()
            ->delete('{{%escapedam_importedfiles}}', [
                'createdByUtility' => true,
            ])
            ->execute();

        Craft::$app->getSession()->setNotice("Deleted {$affectedRows} records that had been created by this utility");

        return $this->redirectToPostedUrl();
    }

}
