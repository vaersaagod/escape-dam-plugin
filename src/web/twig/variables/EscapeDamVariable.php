<?php


namespace escape\escapedam\web\twig\variables;

use craft\elements\Asset;

use escape\escapedam\EscapeDam;
use escape\escapedam\models\Settings;

/**
 * Class EscapeDamVariable
 * @package escape\escapedam\web\twig\variables
 */
class EscapeDamVariable
{
    /**
     * @return string
     * @throws \craft\errors\SiteNotFoundException
     */
    public function getDamToken()
    {
        return EscapeDam::$plugin->users->getDamToken();
    }

    /**
     * @return string|null
     */
    public function getDamUrl()
    {
        /** @var Settings $settings */
        $settings = EscapeDam::$plugin->getSettings();
        return $settings->damUrl;
    }

    /**
     * @return Settings
     */
    public function getSettings(): Settings
    {
        return EscapeDam::getInstance()->getSettings();
    }

    /**
     * @param Asset $asset
     * @return mixed|null
     */
    public function getFileForImportedAsset(Asset $asset)
    {
        return EscapeDam::getInstance()->files->getFileForImportedAsset($asset);
    }
}
