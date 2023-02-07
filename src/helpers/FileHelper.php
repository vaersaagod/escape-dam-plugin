<?php

namespace escape\escapedam\helpers;

use Craft;

class FileHelper
{
    /**
     * @param $fileUrl
     * @param $filePath
     * @return bool
     * @throws \Exception
     */
    public static function downloadFile($fileUrl, $filePath)
    {

        $httpStatus = null;
        $errorMessage = null;

        if (\function_exists('curl_init')) {

            $ch = \curl_init($fileUrl);
            $fp = \fopen($filePath, "wb");

            $options = [
                CURLOPT_FILE => $fp,
                CURLOPT_HEADER => 0,
                CURLOPT_FOLLOWLOCATION => 1,
                CURLOPT_TIMEOUT => 60,
            ];

            \curl_setopt_array($ch, $options);
            \curl_exec($ch);

            if (\curl_errno($ch) !== 0) {
                $errorMessage = \curl_error($ch);
            }

            $httpStatus = (int) \curl_getinfo($ch, CURLINFO_HTTP_CODE);

            \curl_close($ch);
            \fclose($fp);

        } elseif (\ini_get('allow_url_fopen')) {

            \file_put_contents($filePath, \file_get_contents($fileUrl));
            $httpStatus = $http_response_header[0] ?? null;
        } else {

            throw new \Exception('Looks like allow_url_fopen is off and cURL is not enabled. To download external files, one of these methods has to be enabled.');
        }

        if ($errorMessage) {
            throw new \Exception(Craft::t('site', 'An error “{errorMessage}” occurred while attempting to download “{fileUrl}”', [
                'fileUrl' => $fileUrl,
                'errorMessage' => $errorMessage
            ]));
        }

        if ($httpStatus !== 200) {
            throw new \Exception(Craft::t('site', 'HTTP status “{httpStatus}” encountered while attempting to download “{fileUrl}”', [
                'fileUrl' => $fileUrl,
                'httpStatus' => $httpStatus
            ]));
        }

        return true;
    }
}
