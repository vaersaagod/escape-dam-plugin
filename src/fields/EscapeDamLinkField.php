<?php


namespace escape\escapedam\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\elements\Asset;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use escape\escapedam\assetbundles\fields\escapedamlink\EscapeDamLinkAsset;
use escape\escapedam\EscapeDam;

/**
 * Class DamLinkField
 * @package escape\escapedam\fields
 */
class EscapeDamLinkField extends Field
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return 'Escape DAM Link';
    }

    /**
     * @inheritdoc
     */
    public static function hasContentColumn(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    protected function inputHtml($value, ElementInterface $element = null): string
    {
        if (!$element || !$element->id || !$element instanceof Asset) {
            return '';
        }
        $importedFile = EscapeDam::getInstance()->files->getFileForImportedAsset($element);
        if (!$importedFile) {
            return '';
        }
        $settings = EscapeDam::$plugin->getSettings();
        if (!$settings->damUrl) {
            return '';
        }
        Craft::$app->getView()->registerAssetBundle(EscapeDamLinkAsset::class);
        $url = UrlHelper::url("{$settings->damUrl}/edit/{$importedFile['id']}");
        return Html::a('Open in DAM', $url, [
            'target' => '_blank',
            'rel' => 'noopener noreferrer',
            'class' => 'btn',
            'data-icon' => 'external',
        ]);
    }

}
