<?php


namespace escape\escapedam\controllers;

use craft\web\Controller;
use escape\escapedam\EscapeDam;

class TokenController extends Controller
{
    /**
     * @return string
     * @throws \craft\errors\SiteNotFoundException
     */
    public function actionGetToken()
    {
        return EscapeDam::getInstance()->users->getDamToken();
    }
}
