<?php
namespace escape\escapedam\controllers;

use Craft;
use craft\web\Controller;

use escape\escapedam\EscapeDam;

use yii\web\Response;

/**
 * Class FilesController
 * @package escape\escapedam\controllers
 */
class FilesController extends Controller
{

    /**
     * @return Response
     * @throws BadRequestHttpException
     */
    public function actionImportFile(): Response
    {

        $this->requireCpRequest();
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();

        $fileId = (int)$request->getRequiredParam('fileId');
        $folderId = (int)$request->getParam('folderId') ?: null;
        $fieldId = (int)$request->getParam('fieldId') ?: null;
        $siteId = (int)$request->getParam('siteId') ?: null;
        $elementId = (int)$request->getParam('elementId') ?: null;

        // Has the file already been imported?
        $asset = EscapeDam::getInstance()->files->getImportedAsset($fileId, $fieldId, $elementId);

        if (!$asset) {
            try {
                $asset = EscapeDam::getInstance()->files->importFile($fileId, $fieldId, $elementId, $siteId, $folderId);
            } catch (\Throwable $e) {
                Craft::error('An error occurred when importing a DAM file: ' . $e->getMessage(), __METHOD__);
                Craft::$app->getErrorHandler()->logException($e);
                return $this->asErrorJson($e->getMessage());
            }
        }

        return $this->asJson([
            'success' => true,
            'filename' => $asset->filename,
            'assetId' => (int)$asset->id
        ]);
        
    }


}
