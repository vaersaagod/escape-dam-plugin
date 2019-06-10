<?php


namespace escape\escapedam\assetbundles\fields\escapedam;


use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class EscapeDamFieldAsset extends AssetBundle
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

        /*$this->js = [
            'tourfield.js',
        ];*/

        $this->css = [
            //'cpsection.css',
        ];

        parent::init();
    }
}
