<?php
namespace escape\escapedam\assetbundles\fields\escapedamlink;

use escape\escapedam\EscapeDam;
use escape\escapedam\models\Settings;

use craft\helpers\Json;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;
use craft\web\View;

class EscapeDamLinkAsset extends AssetBundle
{

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = "@escape/escapedam/assetbundles/fields/escapedamlink/resources";

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [

        ];

        $this->css = [
            'EscapeDamLink.css',
        ];

        parent::init();
    }
}
