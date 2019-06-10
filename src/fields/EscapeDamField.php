<?php


namespace escape\escapedam\fields;


use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\elements\db\ElementQuery;
use craft\fields\Assets;
use craft\helpers\Template;

class EscapeDamField extends Assets
{

    /**
     * @var string|null Where files should be restricted to, in format
     * "folder:X", where X is the craft\models\VolumeFolder ID
     */
    public $damImportLocationSource;

    /**
     * @var string|null The subpath that files should be restricted to
     */
    public $damImportLocationSubpath;

    /**
     * @var string|null The label for the DAM selection input button
     */
    public $damSelectionLabel;

    /**
     * @var bool|null If selecting and uploading via standard Assets should be allowed
     */
    public $enableAssetsInput;

    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        if ((\debug_backtrace()[1]['function'] ?? null) === 'twig_get_attribute') {
            return Craft::t('app', 'Assets');
        }
        return Craft::t('app', 'Escape DAM');
    }

    /**
     * @return string
     */
    public static function defaultSelectionLabel(): string
    {
        return Craft::t('escapedam', 'Select from Assets');
    }

    /**
     * @return string
     */
    public static function defaultDamSelectionLabel(): string
    {
        return Craft::t('escapedam', 'Select from DAM');
    }

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->settingsTemplate = 'escapedam/_components/fields/EscapeDamField_settings';
        $this->inputTemplate = 'escapedam/_components/fields/EscapeDamField_input';
        //$this->inputJsClass = 'Craft.AssetSelectInput';
    }

    /**
     * @inheritdoc
     */
    protected function inputTemplateVariables($value = null, ElementInterface $element = null): array
    {
        $variables = parent::inputTemplateVariables($value, $element);
        return \array_merge($variables, [
            'enableAssetsInput' => $this->enableAssetsInput,
            'damSelectionLabel' => $this->damSelectionLabel ?: self::defaultDamSelectionLabel(),
        ]);
    }
}
