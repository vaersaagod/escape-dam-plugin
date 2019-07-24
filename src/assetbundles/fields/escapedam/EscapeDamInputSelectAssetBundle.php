<?php
namespace escape\escapedam\assetbundles\fields\escapedam;

use escape\escapedam\EscapeDam;
use escape\escapedam\models\Settings;

use craft\helpers\Json;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;
use craft\web\View;

class EscapeDamInputSelectAssetBundle extends AssetBundle
{

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = "@escape/escapedam/assetbundles/fields/escapedam/resources";

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'EscapeDamSelectorModal.js',
            'EscapeDamInputSelect.js',
        ];

        $this->css = [
            'EscapeDamInputSelect.css',
        ];

        parent::init();
    }
}
