<?php

namespace escape\escapedam\utilities;

use Craft;
use craft\base\Utility;

/**
 * Class EscapeDam
 */
class EscapeDam extends Utility
{

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('app', 'Escape DAM');
    }

    /**
     * @inheritdoc
     */
    public static function id(): string
    {
        return 'escapedam';
    }

    /**
     * @inheritdoc
     */
    public static function iconPath()
    {
        return Craft::getAlias('@escape/escapedam/icon.svg');
    }

    /**
     * @inheritdoc
     */
    public static function contentHtml(): string
    {
        return Craft::$app->getView()->renderTemplate('escapedam/_components/utility/fix-missing-importedfiles-records');
    }

}
