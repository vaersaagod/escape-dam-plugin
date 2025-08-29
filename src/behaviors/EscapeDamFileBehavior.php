<?php

namespace escape\escapedam\behaviors;

use Craft;

use craft\elements\Asset;
use craft\helpers\Json;
use craft\helpers\Template;
use craft\helpers\UrlHelper;
use craft\web\View;

use escape\escapedam\EscapeDam;
use escape\escapedam\helpers\MuxHelper;

use yii\base\Behavior;

/**
 *
 */
class EscapeDamFileBehavior extends Behavior
{

    /** @var array|null */
    private ?array $_damFileData;

    /** @var string|null */
    private ?string $_muxPlaybackId = null;

    /** @var array|null */
    private ?array $_damVideoData;

    /**
     * @return array|null
     */
    public function getDamFileData(): ?array
    {
        /** @var Asset $asset */
        $asset = $this->owner;
        if (!isset($this->_damFileData)) {
            $data = EscapeDam::getInstance()->files->getFileForImportedAsset($asset);
            if (!is_array($data) || empty($data['id'])) {
                $this->_damFileData = null;
            } else {
                $this->_damFileData = $data;
            }
        }
        return $this->_damFileData;
    }

    /**
     * @return bool
     */
    public function isDamFile(): bool
    {
        return !empty($this->getDamFileData());
    }

    /**
     * @return bool
     */
    public function isDamImage(): bool
    {
        /** @var Asset $asset */
        $asset = $this->owner;
        return $this->isDamFile() && $asset->kind === Asset::KIND_IMAGE;
    }

    /**
     * @return bool
     */
    public function isDamVideo(): bool
    {
        /** @var Asset $asset */
        $asset = $this->owner;
        return $this->isDamFile() && $asset->kind === Asset::KIND_JSON;
    }

    /**
     * @return int|null
     */
    public function getDamId(): ?int
    {
        $data = $this->getDamFileData();
        if (empty($data)) {
            return null;
        }
        return $data['id'];
    }

    /**
     * @return string|null
     */
    public function getDamUrl(): ?string
    {
        $data = $this->getDamFileData();
        if (empty($data)) {
            return null;
        }
        $damUrl = EscapeDam::getInstance()->getSettings()->damUrl ?? '';
        if (!UrlHelper::isAbsoluteUrl($damUrl)) {
            Craft::error('No DAM URL in settings', __METHOD__);
            return null;
        }
        return rtrim($damUrl, '/') . '/edit/' . $data['id'];
    }

    /**
     * @return string|null
     * @throws \yii\base\InvalidConfigException
     */
    public function getMuxPlaybackId(): ?string
    {
        if (!isset($this->_muxPlaybackId)) {
            $videoData = $this->getDamVideoData() ?? [];
            $this->_muxPlaybackId = $videoData['muxPlaybackId'] ?? null;
        }
        return $this->_muxPlaybackId;
    }

    /**
     * @return string|null
     * @throws \yii\base\InvalidConfigException
     */
    public function getDamVideoStreamUrl(): ?string
    {
        return MuxHelper::getStreamUrl($this->getMuxPlaybackId());
    }

    /**
     * @return array|null
     * @throws \yii\base\InvalidConfigException
     */
    public function getDamVideoData(): ?array
    {
        if (!isset($this->_damVideoData)) {
            $this->_damVideoData = $this->_getDamVideoData();
        }
        return $this->_damVideoData;
    }

    /**
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \yii\base\Exception
     */
    public function getDamVideoTag(array $params = [], bool $polyfill = true, bool $lazyload = true): ?\Twig\Markup
    {
        if (!$streamUrl = $this->getDamVideoStreamUrl()) {
            return null;
        }
        if (!isset($params['poster'])) {
            $params['poster'] = MuxHelper::getImageUrl($this->getMuxPlaybackId());
        }
        if (isset($params['id'])) {
            $id = $params['id'];
            unset($params['id']);
        }
        $lazyloadDelay = EscapeDam::getInstance()->getSettings()->hlsVideoLazyloadDelay;
        return Template::raw(Craft::$app->getView()->renderTemplate('escapedam/hls-video-tag.twig', [
            'id' => $id ?? null,
            'src' => $streamUrl,
            'polyfill' => $polyfill,
            'lazyload' => $lazyload,
            'lazyloadDelay' => $lazyloadDelay,
            'attributes' => $params,
        ], View::TEMPLATE_MODE_CP));
    }

    /**
     * @param array $params
     * @return string|null
     * @throws \yii\base\InvalidConfigException
     */
    public function getDamVideoImageUrl(array $params = []): ?string
    {
        return MuxHelper::getImageUrl($this->getMuxPlaybackId(), $params);
    }

    /**
     * @param array $params
     * @return string
     * @throws \yii\base\InvalidConfigException
     */
    public function getDamVideoGifUrl(array $params = []): string
    {
        return MuxHelper::getGifUrl($this->getMuxPlaybackId(), $params);
    }

    /**
     * @return array|null
     * @throws \yii\base\InvalidConfigException
     */
    private function _getDamVideoData(): ?array
    {
        if (!$this->isDamVideo()) {
            return null;
        }
        /** @var Asset $asset */
        $asset = $this->owner;
        $data = Json::decodeIfJson(EscapeDam::getInstance()->files->getContents($asset));
        if (empty($data) || !is_array($data)) {
            return null;
        }
        return $data;
    }

}
