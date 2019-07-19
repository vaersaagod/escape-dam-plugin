<?php


namespace escape\escapedam\services;

use Craft;
use craft\base\Component;

use craft\helpers\DateTimeHelper;
use escape\escapedam\EscapeDam;
use escape\escapedam\models\Settings;

use \Firebase\JWT\JWT;

class Users extends Component
{
    /**
     * @return string
     * @throws \craft\errors\SiteNotFoundException
     */
    public function getDamToken(): string
    {
        $user = Craft::$app->getUser()->getIdentity();
        if (!$user) {
            throw new \Exception('User is not logged in');
        }
        /** @var Settings $settings */
        $settings = EscapeDam::$plugin->getSettings();
        $jwtSecret = $settings->jwtSecret;
        $now = DateTimeHelper::currentTimeStamp();
        $payload = [
            'iat' => $now,
            'exp' => \date_modify(DateTimeHelper::toDateTime($now), '+5 minutes')->getTimestamp(),
            'iss' => Craft::$app->getSites()->getCurrentSite()->getBaseUrl(),
            'user' => [
                'email' => $user->email,
                'firstName' => $user->firstName,
                'lastName' => $user->lastName,
            ],
        ];
        return JWT::encode($payload, $jwtSecret);
    }
}
