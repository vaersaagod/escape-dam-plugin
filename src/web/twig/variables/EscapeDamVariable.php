<?php


namespace escape\escapedam\web\twig\variables;


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
}
