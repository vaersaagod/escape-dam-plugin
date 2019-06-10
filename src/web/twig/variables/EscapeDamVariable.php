<?php


namespace escape\escapedam\web\twig\variables;


use escape\escapedam\EscapeDam;

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
}
