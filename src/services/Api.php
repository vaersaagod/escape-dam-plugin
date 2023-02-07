<?php


namespace escape\escapedam\services;


use Craft;
use craft\base\Component;
use craft\elements\Asset;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use escape\escapedam\EscapeDam;
use escape\escapedam\helpers\ApiHelper;
use escape\escapedam\models\Settings;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

use Psr\Http\Message\ResponseInterface;

class Api extends Component
{

    /** @var Client|null */
    public $client;

    /** @var array|null */
    protected $sites;

    private ?int $_userId = null;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        /** @var Settings $settings */
        $settings = EscapeDam::getInstance()->getSettings();

        if ($this->client === null) {
            $this->client = Craft::createGuzzleClient([
                'base_uri' => \rtrim($settings->damUrl, '/') . '/',
            ]);
        }
    }

    /**
     * Sets a User to the API service
     * This user will act as the current user for subsequent API requests
     * We have to do this to generate bearer tokens in console requests, which can't read the session cookie
     */
    public function setUser(User $user)
    {
        $this->setUserId($user->getId());
    }

    public function setUserId(int $userId)
    {
        $this->_userId = $userId;
    }

    public function getSites(): array
    {
        if ($this->sites === null) {
            $response = $this->request('GET', 'actions/escape-dam-module/default/get-sites');
            $body = Json::decode((string)$response->getBody());
            $this->sites = $body['data'] ?? [];
        }
        return $this->sites;
    }

    public function getSiteIds(): array
    {
        $sites = $this->getSites() ?? [];
        return \array_map(fn(array $site) => (int)$site['id'], $sites);
    }

    /**
     * @param $id
     */
    public function getFileDetailsById($id): array
    {
        $data = [];
        $siteIds = $this->getSiteIds();
        foreach ($siteIds as $siteId) {
            $response = $this->request('GET', "api/assets/$id?siteId=$siteId");
            $body = Json::decode((string)$response->getBody());
            $values = \array_reduce(\array_keys($body), function (array $carry, string $key) use ($data, $body) {
                // Discard fields that are irrelevant, unwanted or will be part of the localized data attribute
                if (\in_array($key, ['url', 'damFilename', 'altText', 'description', 'credit', 'categories', 'userHasEdited', 'isFavorite', 'useCount'])) {
                    return $carry;
                }
                $carry[$key] = $data[$key] ?? $body[$key] ?? null;
                return $carry;
            }, []);
            $data = \array_merge($values, [
                'localizedData' => \array_merge($data['localizedData'] ?? [], [
                    $body['language'] => [
                        'url' => $body['url'],
                        'filename' => ($body['damFilename'] ?? null) ? $body['damFilename'] . '.' . $body['extension'] : ($body['originalFilename'] ?? $body['filename']),
                        'fieldValues' => [
                            'altText' => $body['altText'],
                            'description' => $body['description'],
                            'credit' => $body['credit'],
                        ],
                    ],
                ]),
            ]);
        }
        return $data;
    }

    /**
     * Query for DAM files matching a given Asset by extension and filename
     *
     * @return array|mixed|null
     * @throws \yii\base\InvalidConfigException
     */
    public function queryForOriginalDamFileByAsset(Asset $asset)
    {

        // Figure out which DAM site to use
        $damSiteId = null;
        $site = $asset->getSite();
        $damSites = $this->getSites();

        foreach ($damSites as $damSite) {
            $damSiteLanguage = $damSite['language'];
            if ($damSiteLanguage === $site->language || \in_array($site->language, ApiHelper::LANGUAGE_CODE_MAP[$damSiteLanguage] ?? [])) {
                $damSiteId = (int)$damSite['id'];
                break;
            }
        }

        if (!$damSiteId) {
            throw new \Exception('No DAM site ID (couldn\'t match language)');
        }

        $filename = $asset->getFilename(false);
        $dateCreated = $asset->dateCreated->format('Y-m-d');

        $criteria = [
            'siteId' => $damSiteId,
            'damFilename' => $filename,
            'dateCreated' => "<= {$dateCreated}"
        ];

        $response = $this->request('GET', 'api/assets/query', [
            'query' => [
                'siteId' => $damSiteId,
                'criteria' => $criteria,
            ],
        ]);

        $responseJson = (string)$response->getBody();

        if (!$responseJson || !Json::isJsonObject($responseJson)) {
            return null;
        }

        $body = Json::decode((string)$response->getBody());
        if (empty($body) || !\is_array($body)) {
            return null;
        }

        $damFiles = $body['data'] ?? [];
        if (empty($damFiles) || !\is_array($damFiles)) {
            return null;
        }

        return $damFiles;

    }

    /**
     * @throws RequestException
     */
    public function request(string $method, string $uri, array $options = []): ResponseInterface
    {

        $options = ArrayHelper::merge($options, [
            'headers' => [
                'Authorization' => 'Bearer ' . EscapeDam::getInstance()->users->getDamToken($this->_userId),
                'Accept' => 'application/json',
                'X-Craft-System' => 'craft:' . Craft::$app->getVersion() . ';' . strtolower((string) Craft::$app->getEditionName()),
            ],
        ]);

        $e = null;

        try {
            $response = $this->client->request($method, $uri, $options);
        } catch (RequestException $e) {
            if (($response = $e->getResponse()) === null || $response->getStatusCode() === 500) {
                throw $e;
            }
        }

        return $response;
    }

}
