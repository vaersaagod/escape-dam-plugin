<?php


namespace escape\escapedam\assetbundles\cpsection;


use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class CpSectionBundle extends AssetBundle
{

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = "@escape/escapedam/assetbundles/cpsection/resources";

        $this->depends = [
            CpAsset::class,
        ];

        /*$this->js = [
            'tourfield.js',
        ];*/

        $this->css = [
            'cpsection.css',
        ];

        parent::init();
    }
}
