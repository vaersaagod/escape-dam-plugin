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

use Craft;
use craft\base\Element;
use craft\base\Plugin;
use craft\elements\Asset;
use craft\elements\db\ElementQuery;
use craft\events\ElementEvent;
use craft\events\PopulateElementEvent;
use craft\events\PluginEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterElementTableAttributesEvent;
use craft\events\SetElementTableAttributeHtmlEvent;
use craft\events\TemplateEvent;
use craft\helpers\ElementHelper;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use craft\services\Elements;
use craft\services\Fields;
use craft\services\Plugins;
use craft\services\Utilities;
use craft\web\Application;
use craft\web\twig\variables\CraftVariable;
use craft\web\View;

use escape\escapedam\assetbundles\cp\EscapeDamCpAsset;
use escape\escapedam\fields\EscapeDamField;
use escape\escapedam\fields\EscapeDamLinkField;
use escape\escapedam\models\Settings;
use escape\escapedam\services\Api;
use escape\escapedam\services\Files;
use escape\escapedam\services\Users;
use escape\escapedam\utilities\EscapeDam as EscapeDamUtility;
use escape\escapedam\web\twig\variables\EscapeDamVariable;

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
    public $schemaVersion = '1.2.0';

    /**
     * @var bool
     */
    public $hasCpSection = true;

    /**
     * @var bool
     */
    public $hasCpSettings = false;

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

        // Register fieldtypes
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = EscapeDamField::class;
                $event->types[] = EscapeDamLinkField::class;
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
            }
        );

        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_SAVE_ELEMENT,
            function (ElementEvent $event) {
                /** @var Element $element */
                $element = $event->element;
                // Get any DAM fields associated with this element, and make sure imported Asset records for this element is updated with the element's ID
                if (ElementHelper::isDraftOrRevision($element) || !$fieldLayout = $element->getFieldLayout()) {
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
                        if (Craft::$app->getConfig()->getGeneral()->devMode) {
                            throw $e;
                        }
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

        Craft::$app->getView()->hook('cp.assets.edit.meta', function (array $context) {
            return Craft::$app->getView()->renderTemplate('escapedam/_components/hooks/element-edit-meta', $context);
        });

        Event::on(Asset::class, Element::EVENT_REGISTER_TABLE_ATTRIBUTES, function (RegisterElementTableAttributesEvent $event) {
            $event->tableAttributes['_escapedam_url'] = Craft::t('site', 'DAM Link');
        });

        Event::on(Asset::class, Element::EVENT_SET_TABLE_ATTRIBUTE_HTML, function (SetElementTableAttributeHtmlEvent $event) {
            $attribute = $event->attribute;
            if ($attribute === '_escapedam_url') {
                /** @var Asset $asset */
                $asset = $event->sender;
                $damFile = EscapeDam::getInstance()->files->getFileForImportedAsset($asset);
                if ($damFile) {
                    $damUrl = EscapeDam::getInstance()->getSettings()->damUrl;
                    if ($damUrl) {
                        $fileUrl = UrlHelper::url(\rtrim($damUrl, '/')) . "/edit/{$damFile['id']}";
                        $event->html = Html::a('Open in DAM', $fileUrl, [
                            'target' => '_blank',
                            'rel' => 'noopener noreferrer',
                            'class' => 'btn',
                            'data-icon' => 'external',
                        ]);
                    } else {
                        $event->html = '';
                    }
                } else {
                    $event->html = '';
                }
                $event->handled = true;
            }
        });

        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITY_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = EscapeDamUtility::class;
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
