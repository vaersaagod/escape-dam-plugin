<?php


namespace escape\escapedam\elements;

use Craft;
use craft\base\Element;
use craft\base\Volume;
use craft\elements\Asset;
use yii\base\NotSupportedException;

class DamAsset extends Asset
{
    public function getEditorHtml(): string
    {

        $view = Craft::$app->getView();

        if (!$this->fieldLayoutId) {
            $this->fieldLayoutId = Craft::$app->getRequest()->getBodyParam('defaultFieldLayoutId');
        }

        $html = '';

        // See if we can show a thumbnail
        try {
            $assetsService = Craft::$app->getAssets();
            $srcsets = [];
            $thumbSizes = [
                [380, 190],
                [760, 380],
            ];
            foreach ($thumbSizes as list($width, $height)) {
                $thumbUrl = $assetsService->getThumbUrl($this, $width, $height, false, false);
                $srcsets[] = $thumbUrl . ' ' . $width . 'w';
            }

            // Is the image editable, and is the user allowed to edit?
            $userSession = Craft::$app->getUser();

            /** @var Volume $volume */
            $volume = $this->getVolume();

            $editable = (
                $this->getSupportsImageEditor() &&
                $userSession->checkPermission('deleteFilesAndFoldersInVolume:' . $volume->uid) &&
                $userSession->checkPermission('saveAssetInVolume:' . $volume->uid)
            );

            $html .= '<div class="image-preview-container' . ($editable ? ' editable' : '') . '">' .
                '<div class="image-preview">' .
                '<img sizes="' . $thumbSizes[0][0] . 'px" srcset="' . implode(', ', $srcsets) . '" alt="">' .
                '</div>';

            if ($editable) {
                $html .= '<div class="btn">' . Craft::t('app', 'Edit') . '</div>';
            }

            $html .= '</div>';
        } catch (NotSupportedException $e) {
            // NBD
        }

        $html .= $view->renderTemplateMacro('_includes/forms', 'textField', [
            [
                'label' => Craft::t('app', 'Filename'),
                'id' => 'newFilename',
                'name' => 'newFilename',
                'value' => $this->filename,
                'errors' => $this->getErrors('newLocation'),
                'first' => true,
                'required' => true,
                'class' => 'renameHelper text'
            ]
        ]);

        $html .= $view->renderTemplateMacro('_includes/forms', 'textField', [
            [
                'label' => Craft::t('app', 'Title'),
                'siteId' => $this->siteId,
                'id' => 'title',
                'name' => 'title',
                'value' => $this->title,
                'errors' => $this->getErrors('title'),
                'required' => true
            ]
        ]);

        $html .= $view->renderTemplateMacro('_includes/forms', 'lightswitchField', [
            [
                'label' => Craft::t('escapedam', 'Use custom data'),
                'id' => 'useCustomData',
                'name' => 'useCustomData',
                'value' => false,
                'errors' => $this->getErrors('useCustomData'),
                'first' => true,
                'required' => true,
                'instructions' => Craft::t('escapedam', 'Enable to override the DAM image metadata (i.e. alternative text, caption, focal point etc.'),
                //'class' => 'renameHelper text'
            ]
        ]);

        $baseGetEditorHtmlMethod = new \ReflectionMethod(Element::class, 'getEditorHtml');
        $html .= $baseGetEditorHtmlMethod->invoke($this);

        return $html;
    }
}
