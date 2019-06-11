<?php


namespace escape\escapedam\assetbundles\fields\escapedam;


use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class EscapeDamInputSelectAsset extends AssetBundle
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
