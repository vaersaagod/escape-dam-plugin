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
        $folderId = (int)$request->getParam('folderId') ?: null;
        $fieldId = (int)$request->getParam('fieldId') ?: null;
        $siteId = (int)$request->getParam('siteId') ?: null;
        $elementId = (int)$request->getParam('elementId') ?: null;

        try {
            $asset = EscapeDam::getInstance()->files->importFile($fileId, $fieldId, $elementId, $siteId, $folderId);
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
