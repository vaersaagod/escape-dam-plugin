<?php

namespace escape\escapedam\assetpreviews;

use craft\assetpreviews\Text;
use craft\base\AssetPreviewHandler;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use craft\web\View;

class EscapeDamVideo extends Text
{
    /**
     * @inheritdoc
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
