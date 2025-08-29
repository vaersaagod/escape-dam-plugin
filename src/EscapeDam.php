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
use craft\base\Model;
use craft\base\Plugin;
use craft\elements\Asset;
use craft\events\AssetPreviewEvent;
use craft\events\DefineAssetThumbUrlEvent;
use craft\events\DefineBehaviorsEvent;
use craft\events\DefineElementInnerHtmlEvent;
use craft\events\DefineHtmlEvent;
use craft\events\DefineAttributeHtmlEvent;
use craft\events\ElementEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterElementTableAttributesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\App;
use craft\helpers\Cp;
use craft\helpers\ElementHelper;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\log\MonologTarget;
use craft\services\Assets;
use craft\services\Elements;
use craft\services\Fields;
use craft\services\Utilities;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;

use escape\escapedam\assetbundles\cp\EscapeDamCpAsset;
use escape\escapedam\assetpreviews\EscapeDamVideo;
use escape\escapedam\behaviors\EscapeDamFileBehavior;
use escape\escapedam\fields\EscapeDamField;
use escape\escapedam\helpers\MuxHelper;
use escape\escapedam\models\Settings;
use escape\escapedam\services\Api;
use escape\escapedam\services\Files;
use escape\escapedam\services\Users;
use escape\escapedam\utilities\EscapeDam as EscapeDamUtility;
use escape\escapedam\web\twig\variables\EscapeDamVariable;

use Psr\Log\LogLevel;
use yii\base\Event;

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

    /** @var string */
    public string $schemaVersion = '1.2.0';

    /** @var bool */
    public bool $hasCpSection = true;

    /** @var Settings|null */
    private ?Settings $_settings = null;

    // Public Methods
    // =========================================================================

    /** @inheritdoc */
    public function init(): void
    {

        parent::init();

        // Custom log target
        Craft::getLogger()->dispatcher->targets[] = new MonologTarget([
            'name' => 'escapedam',
            'categories' => ['escapedam', 'escape\\escapedam\\*'],
            'extractExceptionTrace' => !App::devMode(),
            'allowLineBreaks' => App::devMode(),
            'level' => App::devMode() ? LogLevel::INFO : LogLevel::WARNING,
            'logContext' => false,
            'maxFiles' => 10,
        ]);

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
            static function (RegisterComponentTypesEvent $event) {
                $event->types[] = EscapeDamField::class;
            }
        );

        // Register template variables
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            static function (Event $event) {
                $variable = $event->sender;
                $variable->set('escapedam', EscapeDamVariable::class);
            }
        );

        // Register CP asset bundle
        if (\Craft::$app->getRequest()->getIsCpRequest() && !\Craft::$app->getRequest()->getIsLoginRequest()) {
            \Craft::$app->onInit(static function () {
                try {
                    $user = Craft::$app->getUser()->getIdentity();
                    if (!$user || !$user->can('accessCp')) {
                        return;
                    }
                    \Craft::$app->getView()->registerAssetBundle(EscapeDamCpAsset::class);
                } catch (\Throwable $e) {
                    \Craft::error($e, __METHOD__);
                }
            });
        }

        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_SAVE_ELEMENT,
            static function (ElementEvent $event) {
                /** @var Element $element */
                $element = $event->element;
                // Get any DAM fields associated with this element, and make sure imported Asset records for this element is updated with the element's ID
                if (ElementHelper::isDraftOrRevision($element) || !$fieldLayout = $element->getFieldLayout()) {
                    return;
                }
                $fields = $fieldLayout->getCustomFields();
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
                        EscapeDam::getInstance()->files->relateImportedAssetToElement($assetIds, (int)$field->id, (int)$element->getId());
                    } catch (\Throwable $e) {
                        Craft::$app->getErrorHandler()->logException($e);
                        if (Craft::$app->getConfig()->getGeneral()->devMode) {
                            throw $e;
                        }
                    }
                }
            }
        );

        Event::on(
            Element::class,
            Element::EVENT_DEFINE_META_FIELDS_HTML,
            static function (DefineHtmlEvent $event) {
                if (!$event->sender instanceof Asset) {
                    return;
                }
                $event->html .= \Craft::$app->getView()->renderTemplate('escapedam/_hooks/dam-link.twig', ['asset' => $event->sender]);
            }
        );

        Event::on(Asset::class, Element::EVENT_REGISTER_TABLE_ATTRIBUTES, static function (RegisterElementTableAttributesEvent $event) {
            $event->tableAttributes['_escapedam_url'] = Craft::t('site', 'DAM Link');
        });

        Event::on(Asset::class, Element::EVENT_DEFINE_ATTRIBUTE_HTML, static function (DefineAttributeHtmlEvent $event) {
            $attribute = $event->attribute;
            if ($attribute === '_escapedam_url' && $event->sender instanceof Asset && $damUrl = $event->sender->getDamUrl()) {
                $event->html = Html::a('Open in DAM', $damUrl, [
                    'target' => '_blank',
                    'rel' => 'noopener noreferrer',
                    'class' => 'btn small',
                    'data-icon' => 'external',
                ]);
            }
        });

        // Replace asset thumbs for DAM-imported videos (which are stored as json, Embedded Assets-style)
        Event::on(
            Assets::class,
            Assets::EVENT_DEFINE_THUMB_URL,
            function (DefineAssetThumbUrlEvent $event) {
                $asset = $event->asset;
                if ($asset->kind !== Asset::KIND_JSON || !$this->files->isImportedAsset($asset)) {
                    return;
                }
                $contents = $this->files->getContents($asset);
                $data = Json::decodeIfJson($contents);
                if (!$data || !\is_array($data) || !$muxPlaybackId = ($data['muxPlaybackId'] ?? null)) {
                    return;
                }
                $thumbSize = max($event->width, $event->height);
                $event->url = MuxHelper::getImageUrl($muxPlaybackId, ['width' => $thumbSize, 'height' => $thumbSize, 'fit_mode' => 'preserve']);
            }
        );

        // TODO fix deprecated code
        Event::on(
            Cp::class,
            Cp::EVENT_DEFINE_ELEMENT_INNER_HTML,
            static function (DefineElementInnerHtmlEvent $event) {
                $element = $event->element;
                if (!$element instanceof Asset || $element->isFolder || !$element->isDamVideo() || $event->size !== 'large') {
                    return;
                }
                $event->innerHtml = str_replace('class="elementthumb', 'class="elementthumb damvideo', $event->innerHtml);
                $css = <<< CSS
                    .elementthumb.damvideo::before {
                        content: "VIDEO";
                        display: block;
                        position: absolute;
                        background-color: black;
                        color: white;
                        left: 50%;
                        top: 50%;
                        transform: translate(-50%, -50%);
                        pointer-events: none;
                        font-size: 11px;
                        border-radius: 3px;
                        padding: 0 4px;
                    }
                CSS;
                \Craft::$app->getView()->registerCss($css);
            }
        );

        // Replace asset previews for DAM-imported videos, too
        Event::on(
            Assets::class,
            Assets::EVENT_REGISTER_PREVIEW_HANDLER,
            static function (AssetPreviewEvent $event) {
                $asset = $event->asset;
                if ($asset->kind !== Asset::KIND_JSON || !$asset->getMuxPlaybackId()) {
                    return;
                }
                $event->previewHandler = new EscapeDamVideo($asset);
            }
        );

        // Add special sexy Asset behavior for DAM videos (Mux)
        Event::on(
            Asset::class,
            Model::EVENT_DEFINE_BEHAVIORS,
            static function (DefineBehaviorsEvent $event) {
                $event->behaviors['escapeDamFileBehavior'] = ['class' => EscapeDamFileBehavior::class];
            }
        );

        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITIES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = EscapeDamUtility::class;
            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $cpSectionPath = $this->getSettings()->cpSectionPath ?? 'escapedam';
                $event->rules[$cpSectionPath] = ['template' => 'escapedam/_index'];
            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            static function (RegisterUrlRulesEvent $event) {
                $event->rules['escapedam/api/file-usage'] = 'escapedam/api/file-usage';
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
    public function getCpNavItem(): ?array
    {
        $navItem = parent::getCpNavItem();
        if (empty($navItem)) {
            return null;
        }
        $navItem['label'] = $this->getSettings()->pluginName ?? 'Escape DAM';
        $navItem['url'] = $this->getSettings()->cpSectionPath ?? 'escapedam';
        return $navItem;
    }

    /**
     * @return Settings
     */
    public function getSettings(): Settings
    {
        if ($this->_settings === null) {
            $this->_settings = $this->createSettingsModel();
        }
        return $this->_settings;
    }

    /**
     * @return Settings
     */
    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    /**
     * @throws \Twig\Error\LoaderError
     * @throws \yii\base\Exception
     */
    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('escapedam/settings', [
            'settings' => $this->getSettings(),
        ]);
    }

}
