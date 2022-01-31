<?php

namespace escape\escapedam\behaviors;

use Craft;

use craft\elements\Asset;
use craft\helpers\Json;
use craft\helpers\Template;
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
    private $_isDamFile;

    /** @var bool */
    private $_isDamVideo;

    /** @var bool */
    private $_isDamImage;

    /** @var string|null */
    private $_muxPlaybackId;

    /** @var array|null */
    private $_damVideoData;

    /**
     * @return bool
     */
    public function isDamFile(): bool
    {
        if (!isset($this->_isDamFile)) {
            $this->_isDamFile = EscapeDam::getInstance()->files->isImportedAsset($this->owner);
        }
        return $this->_isDamFile;
    }

    /**
     * @return bool
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
     * @return bool
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
     */
    public function getDamVideoStreamUrl(): ?string
    {
        return MuxHelper::getStreamUrl($this->getMuxPlaybackId());
    }

    /**
     * @return array|null
     */
    public function getDamVideoData(): ?array
    {
        if (!isset($this->_damVideoData)) {
            $this->_damVideoData = $this->_getDamVideoData();
        }
        return $this->_damVideoData;
    }

    /**
     * @param array $params
     * @param bool $polyfill
     * @return \Twig\Markup|null
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \yii\base\Exception
     */
    public function getDamVideoTag(array $params = [], bool $polyfill = true): ?\Twig\Markup
    {
        if (!$streamUrl = $this->getDamVideoStreamUrl()) {
            return null;
        }
        if (!isset($params['poster'])) {
            $params['poster'] = MuxHelper::getImageUrl($this->getMuxPlaybackId());
        }
        return Template::raw(Craft::$app->getView()->renderTemplate('escapedam/hls-video-tag.twig', [
            'src' => $streamUrl,
            'polyfill' => $polyfill,
            'attributes' => $params,
        ], View::TEMPLATE_MODE_CP));
    }

    /**
     * @param array $params
     * @return string|null
     */
    public function getDamVideoImageUrl(array $params = []): ?string
    {
        return MuxHelper::getImageUrl($this->getMuxPlaybackId(), $params);
    }

    /**
     * @param array $params
     * @return string
     */
    public function getDamVideoGifUrl(array $params = []): string
    {
        return MuxHelper::getGifUrl($this->getMuxPlaybackId(), $params);
    }

    /**
     * @return array|null
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
