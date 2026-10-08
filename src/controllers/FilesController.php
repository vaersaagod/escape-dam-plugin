<?php
namespace escape\escapedam\controllers;

use Craft;
use craft\web\Controller;

use escape\escapedam\EscapeDam;

use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * Class FilesController
 * @package escape\escapedam\controllers
 */
class FilesController extends Controller
{

    /**
     * @return Response
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\web\BadRequestHttpException
     * @throws \yii\web\MethodNotAllowedHttpException
     */
    public function actionImportFile(): Response
    {

        $this->requireCpRequest();
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();

        $fileId = (int)$request->getRequiredParam('fileId');
        $fieldId = (int)$request->getRequiredParam('fieldId');
        $siteId = (int)$request->getParam('siteId') ?: null;
        $elementId = (int)$request->getParam('elementId') ?: null;

        $files = EscapeDam::getInstance()->files;

        // Files are always imported to the field's import folder, and only by users who can save assets there.
        // For new elements that's the user's own temporary upload folder, which has no volume
        try {
            $folder = $files->getFolderForImportByFieldAndElement($fieldId, $elementId, $siteId);
        } catch (\Throwable $e) {
            Craft::error($e, __METHOD__);
            return $this->asFailure($e->getMessage());
        }

        if (!$folder) {
            throw new BadRequestHttpException('The field has no import folder');
        }

        if ($folder->volumeId) {
            $this->requirePermission("saveAssets:{$folder->getVolume()->uid}");
        }

        try {
            $asset = $files->importFile($fileId, $fieldId, $elementId, $siteId, $folder->id);
        } catch (\Throwable $e) {
            Craft::error($e, __METHOD__);
            return $this->asFailure($e->getMessage());
        }

        return $this->asJson([
            'success' => true,
            'filename' => $asset->getFilename(),
            'assetId' => (int)$asset->getId(),
        ]);
        
    }


}
