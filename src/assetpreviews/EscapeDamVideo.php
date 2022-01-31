<?php

namespace escape\escapedam\assetpreviews;

use craft\assetpreviews\Text;
use craft\base\AssetPreviewHandler;
use craft\helpers\Html;
use craft\helpers\UrlHelper;

class EscapeDamVideo extends Text
{
    /**
     * @inheritdoc
     */
    public function getPreviewHtml(): string
    {
        if (!$this->asset->getMuxPlaybackId()) {
            return parent::getPreviewHtml();
        }
        return Html::modifyTagAttributes($this->asset->getDamVideoTag([
            'controls' => true,
            'autoplay' => true,
            'poster' => false,
        ]), [
            'width' => '100%',
            'height' => '100%',
        ]);
    }
}
