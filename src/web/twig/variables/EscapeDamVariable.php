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
    public function getDamToken(): string
    {
        return EscapeDam::getInstance()->users->getDamToken();
    }

    /**
     * @return string|null
     */
    public function getDamUrl(): ?string
    {
        /** @var Settings $settings */
        $settings = EscapeDam::getInstance()->getSettings();
        return $settings->damUrl;
    }

    public function getSettings(): Settings
    {
        return EscapeDam::getInstance()->getSettings();
    }

    /**
     * @return mixed|null
     */
    public function getFileForImportedAsset(?Asset $asset)
    {
        if (!$asset instanceof Asset) {
            return null;
        }
        return EscapeDam::getInstance()->files->getFileForImportedAsset($asset);
    }
}
