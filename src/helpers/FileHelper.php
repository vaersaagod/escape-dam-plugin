<?php

namespace escape\escapedam\helpers;

use Craft;

final class FileHelper
{
    /**
     * @param $fileUrl
     * @param $filePath
     * @return bool
     * @throws \Exception
     */
    public static function downloadFile($fileUrl, $filePath): bool
    {

        if (empty($fileUrl)) {
            throw new \Exception("File url cannot be empty");
        }

        $errorMessage = null;

        if (!function_exists('curl_init')) {
            throw new \Exception('Curl not installed');
        }

        $ch = curl_init($fileUrl);
        $fp = fopen($filePath, "wb");

        $options = [
            CURLOPT_FILE => $fp,
            CURLOPT_HEADER => 0,
            CURLOPT_FOLLOWLOCATION => 1,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_REFERER => Craft::$app->getSites()->getPrimarySite()->getBaseUrl(),
        ];

        curl_setopt_array($ch, $options);
        curl_exec($ch);

        if (curl_errno($ch) !== 0) {
            $errorMessage = curl_error($ch);
        }

        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        fclose($fp);

        if (!empty($errorMessage)) {
            throw new \Exception(Craft::t('site', 'An error “{errorMessage}” occurred while attempting to download “{fileUrl}”', [
                'fileUrl' => $fileUrl,
                'errorMessage' => $errorMessage,
            ]));
        }

        if ($httpStatus !== 200) {
            throw new \Exception(Craft::t('site', 'HTTP status “{httpStatus}” encountered while attempting to download “{fileUrl}”', [
                'fileUrl' => $fileUrl,
                'httpStatus' => $httpStatus,
            ]));
        }

        return true;
    }
}
