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

        // The URL comes from the DAM API, so only ever download over HTTPS, redirects included
        if (\strtolower((string)\parse_url((string)$fileUrl, PHP_URL_SCHEME)) !== 'https') {
            throw new \Exception("File url must be an HTTPS URL");
        }

        $ch = curl_init($fileUrl);
        $fp = fopen($filePath, "wb");

        $options = [
            CURLOPT_FILE => $fp,
            CURLOPT_HEADER => 0,
            CURLOPT_FOLLOWLOCATION => 1,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 10,
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
