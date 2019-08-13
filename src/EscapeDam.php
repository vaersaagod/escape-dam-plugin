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

use craft\events\ElementEvent;
use craft\services\Elements;
use escape\escapedam\assetbundles\cp\EscapeDamCpAsset;
use escape\escapedam\fields\EscapeDamField;
use escape\escapedam\models\Settings;
use escape\escapedam\services\Api;
use escape\escapedam\services\Files;
use escape\escapedam\services\Users;
use escape\escapedam\web\twig\variables\EscapeDamVariable;

use Craft;
use craft\base\Element;
use craft\base\Plugin;
use craft\elements\Asset;
use craft\elements\db\ElementQuery;
use craft\events\PopulateElementEvent;
use craft\events\PluginEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\TemplateEvent;
use craft\services\Fields;
use craft\services\Plugins;
use craft\web\Application;
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

        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {
                }
            }
        );

        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_LOAD_PLUGINS,
            function () {
                
                // Register Asset bundles
                $request = Craft::$app->getRequest();
                $user = Craft::$app->getUser()->getIdentity();
                if (!$request->getIsCpRequest() || $request->getIsConsoleRequest() || !$user || !$user->can('accessCp')) {
                    return;
                }

                Event::on(
                    View::class,
                    View::EVENT_BEFORE_RENDER_TEMPLATE,
                    function (TemplateEvent $event) {
                        try {
                            Craft::$app->getView()->registerAssetBundle(EscapeDamCpAsset::class);
                        } catch (InvalidConfigException $e) {
                            Craft::error(
                                'Error registering AssetBundle - '.$e->getMessage(),
                                __METHOD__
                            );
                        }
                    }
                );

                Event::on(
                    Elements::class,
                    Elements::EVENT_AFTER_SAVE_ELEMENT,
                    function (ElementEvent $event) {
                        /** @var Element $element */
                        $element = $event->element;
                        // Get any DAM fields associated with this element, and make sure imported Asset records for this element is updated with the element's ID
                        if (!$fieldLayout = $element->getFieldLayout()) {
                            return;
                        }
                        $fields = $fieldLayout->getFields();
                        foreach ($fields as $field) {
                            if (!$field instanceof EscapeDamField) {
                                continue;
                            }
                            $fieldHandle = $field->handle;
                            $assetIds = $element->$fieldHandle->ids();
                            if (empty($assetIds)) {
                                continue;
                            }
                            try {
                                EscapeDam::$plugin->files->relateImportedAssetToElement($assetIds, (int)$field->id, (int)$element->id);
                            } catch (\Throwable $e) {
                                Craft::$app->getErrorHandler()->logException($e);
                            }
                        }
                    }
                );

                /**
                 * Attach a behavior after an Asset has been loaded from the database (populated).
                 */
                /*Event::on(ElementQuery::class, ElementQuery::EVENT_AFTER_POPULATE_ELEMENT, function(PopulateElementEvent $event) {
                    /** @var Element $element */
                    /*$element = $event->element;
                    if (!$element instanceof Asset) {
                        return;
                    }
                    $element->attachBehavior('escapeDamAssetBehavior', AssetBehaviour::class);
                });*/
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
