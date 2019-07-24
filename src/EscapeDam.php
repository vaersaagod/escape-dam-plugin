<?php
/**
 * Escape DAM plugin for Craft CMS 3.x
 *
 * Escape DAM integration
 *
 * @link      https://vaersaagod.no
 * @copyright Copyright (c) 2019 Værsågod
 */

namespace escape\escapedam;

use escape\escapedam\assetbundles\cp\EscapeDamCpAssetBundle;
use escape\escapedam\fields\EscapeDamField;
use escape\escapedam\models\Settings;
use escape\escapedam\services\Api;
use escape\escapedam\services\Files;
use escape\escapedam\services\Users;
use escape\escapedam\web\twig\variables\EscapeDamVariable;

use Craft;
use craft\base\Plugin;
use craft\events\PluginEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\TemplateEvent;
use craft\services\Fields;
use craft\services\Plugins;
use craft\web\twig\variables\CraftVariable;
use craft\web\View;

use yii\base\Event;
use yii\base\InvalidConfigException;

/**
 * Class EscapeDam
 *
 * @author    Værsågod
 * @package   EscapeDam
 * @since     1.0.0
 *
 * @property Api $api
 * @property Files $files
 * @property Users $users
 */
class EscapeDam extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var EscapeDam
     */
    public static $plugin;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $schemaVersion = '1.0.0';

    /**
     * @var bool
     */
    public $hasCpSection = true;

    /**
     * @var bool
     */
    public $hasCpSettings = true;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        // Register services
        $this->setComponents([
            'api' => Api::class,
            'files' => Files::class,
            'users' => Users::class,
        ]);

        // Register fieldtype
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = EscapeDamField::class;
            }
        );

        // Register template variables
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                $variable = $event->sender;
                $variable->set('escapedam', EscapeDamVariable::class);
            }
        );

        // Register Asset bundles
        $request = Craft::$app->getRequest();
        if ($request->getIsCpRequest() && !$request->getIsConsoleRequest()) {
            Event::on(
                View::class,
                View::EVENT_BEFORE_RENDER_TEMPLATE,
                function (TemplateEvent $event) {
                    try {
                        Craft::$app->getView()->registerAssetBundle(EscapeDamCpAssetBundle::class);
                    } catch (InvalidConfigException $e) {
                        Craft::error(
                            'Error registering AssetBundle - '.$e->getMessage(),
                            __METHOD__
                        );
                    }
                }
            );
        }

        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {
                }
            }
        );

        Craft::info(
            Craft::t(
                'escapedam',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    /**
     * @return array|null
     */
    /*public function getCpNavItem()
    {
        $item = parent::getCpNavItem();
        return $item;
    }*/

    // Protected Methods
    // =========================================================================
    /**
     * @return Settings
     */
    protected function createSettingsModel()
    {
        return new Settings();
    }

    /**
     * @return string|null
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     */
    protected function settingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('escapedam/settings', [
            'settings' => $this->getSettings(),
        ]);
    }

}
