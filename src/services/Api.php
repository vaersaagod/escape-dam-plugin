<?php


namespace escape\escapedam\services;


use Craft;
use craft\base\Component;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use escape\escapedam\EscapeDam;
use escape\escapedam\models\Settings;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

class Api extends Component
{

    /**
     * @var array
     */
    protected $siteIds;

    /**
     * @var Client
     */
    public $client;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        /** @var Settings $settings */
        $settings = EscapeDam::$plugin->getSettings();

        if (!isset($this->client)) {
            $this->client = Craft::createGuzzleClient([
                'base_uri' => \rtrim($settings->damUrl, '/') . '/',
            ]);
        }
    }

    /**
     * @return array
     */
    public function getSiteIds(): array
    {
        if (!isset($this->siteIds)) {
            $response = $this->request('GET', 'actions/escape-dam-module/default/get-sites');
            $body = Json::decode((string)$response->getBody());
            $this->siteIds = \array_map(function (array $site) {
                return (int)$site['id'];
            }, $body['data'] ?? []);
        }
        return $this->siteIds;
    }

    /**
     * @param $id
     * @return array
     */
    public function getFileDetailsById($id)
    {
        $data = [];
        $siteIds = $this->getSiteIds();
        foreach ($siteIds as $siteId) {
            $response = $this->request('GET', "api/assets/$id?siteId=$siteId");
            $body = Json::decode((string)$response->getBody());
            $data = \array_merge($data, [
                'id' => (int)$body['id'],
                'url' => $body['assetUrl'],
                'extension' => $body['extension'],
                'dateCreated' => $body['dateCreated'],
                'dateUpdated' => $body['dateUpdated'],
                'captureDate' => $body['captureDate'],
                'mime' => $body['mime'],
                'kind' => $body['kind'],
                'width' => $body['width'],
                'height' => $body['height'],
                'size' => $body['size'],
                'usageRestrictions' => $body['usageRestrictions'],
                'copyrightNotice' => $body['copyrightNotice'],
                'colorPalette' => $body['colorPalette'],
                'focalPoint' => $body['focalPoint'],
                'uploadedBy' => $body['uploadedBy'],
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
     * @param string $method
     * @param string $uri
     * @param array $options
     * @return ResponseInterface
     * @throws RequestException
     */
    public function request(string $method, string $uri, array $options = []): ResponseInterface
    {

        $options = ArrayHelper::merge($options, [
            'headers' => [
                'Authorization' => 'Bearer ' . EscapeDam::$plugin->users->getDamToken(),
                'Accept' => 'application/json',
                'X-Craft-System' => 'craft:' . Craft::$app->getVersion() . ';' . strtolower(Craft::$app->getEditionName()),
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
