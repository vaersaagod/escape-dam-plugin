<?php


namespace escape\escapedam\fields;


use Craft;
use craft\fields\Assets;

class EscapeDamField extends Assets
{
    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('app', 'Escape DAM');
    }
}
