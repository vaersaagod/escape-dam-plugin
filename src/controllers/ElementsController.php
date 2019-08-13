<?php


namespace escape\escapedam\controllers;

use escape\escapedam\elements\DamAsset;

use Craft;
use craft\elements\Asset;
use craft\helpers\ElementHelper;

use yii\web\ForbiddenHttpException;
use yii\web\Response;

class ElementsController extends \craft\controllers\ElementsController
{

    /**
     * @return Response
     * @throws \ReflectionException
     */
    public function actionGetEditorHtml(): Response
    {
        // Get the element
        $nativeController = new \craft\controllers\ElementsController($this->id, $this->module);
        $nativeControllerReflection = new \ReflectionClass(\get_parent_class($this));
        $getEditorElementMethod = $nativeControllerReflection->getMethod('_getEditorElement');
        $getEditorElementMethod->setAccessible(true);
        $element = $getEditorElementMethod->invoke($nativeController);

        if ($element instanceof Asset) {
            $asset = $element;
            $element = new DamAsset();
            $element->uid = $asset->uid;
            $element->setAttributes($asset->getAttributes());
            $element->setFieldValues($asset->getFieldValues());
        }

        $getEditorHtmlResponseMethod = $nativeControllerReflection->getMethod('_getEditorHtmlResponse');
        $getEditorHtmlResponseMethod->setAccessible(true);

        $includeSites = (bool)Craft::$app->getRequest()->getBodyParam('includeSites', false);
        return $getEditorHtmlResponseMethod->invoke($nativeController, $element, $includeSites);
    }
}
