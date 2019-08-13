<?php


namespace escape\escapedam\assetbundles\cp;


use craft\helpers\Json;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;
use craft\web\View;

use escape\escapedam\EscapeDam;
use escape\escapedam\models\Settings;

class EscapeDamCpSectionAsset extends AssetBundle
{

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = "@escape/escapedam/assetbundles/cp/resources";

        $this->depends = [
            CpAsset::class,
        ];

        /*$this->js = [

        ];*/

        $this->css = [
            'cpsection.css',
        ];

        parent::init();
    }
}
