<?php


namespace escape\escapedam\assetbundles\cp;

use craft\helpers\Json;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;
use craft\web\View;
use escape\escapedam\EscapeDam;
use escape\escapedam\models\Settings;

class EscapeDamCpAsset extends AssetBundle
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

        $this->js = [
            //'EscapeDamElementEditor.js',
        ];

        $this->css = [

        ];

        parent::init();
    }

    /**
     * @param \yii\web\View $view
     * @throws \craft\errors\SiteNotFoundException
     */
    public function registerAssetFiles($view)
    {
        parent::registerAssetFiles($view);

        if (!$view instanceof View) {
            return;
        }

        /** @var Settings $settings */
        $settings = EscapeDam::$plugin->getSettings();

        $config = [
            'damUrl' => $settings->damUrl,
            'token' => EscapeDam::$plugin->users->getDamToken(),
        ];
        $configJson = Json::encode($config, JSON_UNESCAPED_UNICODE);
        $js = <<<JS
                    Craft.EscapeDam = Craft.EscapeDam || {};
                    Craft.EscapeDam.settings = {$configJson};
JS;
        $view->registerJs($js, View::POS_HEAD);
    }
}
