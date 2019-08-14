<?php


namespace escape\escapedam\controllers;

use escape\escapedam\elements\DamAsset;

use Craft;
use craft\elements\Asset;

use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class ElementsController extends \craft\controllers\ElementsController
{

    /**
     * @return Response
     * @throws NotFoundHttpException
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionGetAssetFocalPoint(): Response
    {
        $this->requireAcceptsJson();
        $this->requireCpRequest();
        $request = Craft::$app->getRequest();
        $assetId = $request->getRequiredParam('assetId');
        /** @var Asset $asset */
        $asset = Craft::$app->getAssets()->getAssetById((int)$assetId);
        if (!$asset) {
            throw new NotFoundHttpException();
        }
        return $this->asJson([
            'focalPoint' => $asset->focalPoint,
        ]);
    }
}
