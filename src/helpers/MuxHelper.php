<?php

namespace escape\escapedam\helpers;

use craft\helpers\UrlHelper;

class MuxHelper
{

    /** @var string */
    final const MUX_STREAMING_DOMAIN = 'https://stream.mux.com';

    /** @var string */
    final const MUX_IMAGE_DOMAIN = 'https://image.mux.com';

    public static function getStreamUrl(?string $playbackId): ?string
    {
        if (!$playbackId) {
            return null;
        }
        return static::MUX_STREAMING_DOMAIN . '/' . $playbackId . '.m3u8';
    }

    public static function getImageUrl(?string $playbackId, array $params = []): ?string
    {

        if (!$playbackId) {
            return null;
        }

        $format = 'jpg';

        if (isset($params['format'])) {
            $format = \strtolower((string) $params['format']);
            unset($params['format']);
        }

        $format = [
            'png' => 'png',
            'jpg' => 'jpg',
            'jpeg' => 'jpg',
        ][$format] ?? 'jpg';

        $paramsMap = [
            'w' => 'width',
            'h' => 'height',
            'fit' => 'fit_mode',
            'mode' => 'fit_mode',
        ];

        $valuesMap = [
            'fit' => 'preserve',
        ];

        $params = \array_reduce(\array_keys($params), function (array $carry, string $key) use ($params, $paramsMap, $valuesMap) {
            $value = $valuesMap[$params[$key]] ?? $params[$key];
            if (!$value) {
                return $carry;
            }
            $carry[$paramsMap[$key] ?? $key] = $value;
            return $carry;
        }, []);

        if (!isset($params['fit_mode'])) {
            $params['fit_mode'] = ($params['width'] ?? null) && ($params['height']) ? 'smartcrop' : 'preserve';
        }

        return UrlHelper::url(static::MUX_IMAGE_DOMAIN . '/' . $playbackId . "/thumbnail.$format", $params);
    }

    public static function getGifUrl(?string $playbackId, array $params = []): ?string
    {

        if (!$playbackId) {
            return null;
        }

        $paramsMap = [
            'w' => 'width',
            'h' => 'height',
        ];

        $params = \array_reduce(\array_keys($params), function (array $carry, string $key) use ($params, $paramsMap) {
            $carry[$paramsMap[$key] ?? $key] = $params[$key];
            return $carry;
        }, []);

        return UrlHelper::url(static::MUX_IMAGE_DOMAIN . '/' . $playbackId . "/animated.gif", $params);

    }

}
