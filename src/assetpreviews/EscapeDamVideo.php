<?php

namespace escape\escapedam\assetpreviews;

use craft\assetpreviews\Text;
use craft\web\View;

class EscapeDamVideo extends Text
{

    /**
     * @param array $variables
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \yii\base\Exception
     * @throws \yii\base\NotSupportedException
     */
    public function getPreviewHtml(array $variables = []): string
    {
        if (!$this->asset->getMuxPlaybackId()) {
            return parent::getPreviewHtml();
        }
        return \Craft::$app->getView()->renderTemplate('escapedam/mux-video-preview.twig', [
            'video' => $this->asset,
        ], View::TEMPLATE_MODE_CP);
    }
}
