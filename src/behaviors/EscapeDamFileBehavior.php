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

    /** @var bool */
    private bool $_isDamFile;

    /** @var bool */
    private bool $_isDamVideo;

    /** @var bool */
    private bool $_isDamImage;

    /** @var string|null */
    private ?string $_muxPlaybackId = null;

    /** @var array|null */
    private ?array $_damVideoData;

    /**
     * @throws \yii\base\InvalidConfigException
     */
    public function isDamFile(): bool
    {
        /** @var Asset $asset */
        $asset = $this->owner;
        if ($asset->isFolder) {
            return false;
        }
        if (!isset($this->_isDamFile)) {
            $this->_isDamFile = EscapeDam::getInstance()->files->isImportedAsset($asset);
        }
        return $this->_isDamFile;
    }

    /**
     * @throws \yii\base\InvalidConfigException
     */
    public function isDamImage(): bool
    {
        if (!isset($this->_isDamImage)) {
            /** @var Asset $asset */
            $asset = $this->owner;
            $this->_isDamImage = $this->isDamFile() && $asset->kind === Asset::KIND_IMAGE;
        }
        return $this->_isDamImage;
    }

    /**
     * @throws \yii\base\InvalidConfigException
     */
    public function isDamVideo(): bool
    {
        if (!isset($this->_isDamVideo)) {
            /** @var Asset $asset */
            $asset = $this->owner;
            $this->_isDamVideo = $this->isDamFile() && $asset->kind === Asset::KIND_JSON;
        }
        return $this->_isDamVideo;
    }

    /**
     * @return string|null
     */
    public function getDamUrl(): ?string
    {
        $damUrl = EscapeDam::getInstance()->getSettings()->damUrl ?? '';
        if (!UrlHelper::isAbsoluteUrl($damUrl)) {
            return null;
        }
        /** @var Asset $asset */
        $asset = $this->owner;
        $damFile = EscapeDam::getInstance()->files->getFileForImportedAsset($asset);
        if (!$damFile || !is_array($damFile) || !isset($damFile['id'])) {
            return null;
        }
        return rtrim($damUrl, '/') . '/edit/' . $damFile['id'];
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
        $data = Json::decodeIfJson(EscapeDam::getInstance()->files->getContents($asset), true);
        if (empty($data) || !\is_array($data)) {
            return null;
        }
        return $data;
    }

}
