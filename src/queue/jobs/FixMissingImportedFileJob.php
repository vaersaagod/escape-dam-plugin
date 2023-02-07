<?php

namespace escape\escapedam\queue\jobs;

use Craft;
use craft\elements\Asset;
use craft\helpers\App;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\queue\BaseJob;

use escape\escapedam\EscapeDam;
use escape\escapedam\records\ImportedFile;
use yii\queue\RetryableJobInterface;

/**
 * Class FixMissingImportedFileJob
 */
class FixMissingImportedFileJob extends BaseJob implements RetryableJobInterface
{

    /** @var int|null */
    public $userId;

    /** @var int|null */
    public $assetIds;
    
    /** @var int|null */
    public $siteId;

    /**
     * @return int
     */
    public function getTtr()
    {
        return 86400; // 24 hours
    }

    /**
     * @param int $attempt
     * @param \Exception|\Throwable $error
     * @return bool
     */
    public function canRetry($attempt, $error)
    {
        return false;
    }

    /** @inheritdoc */
    public function execute($queue): void
    {

        App::maxPowerCaptain();

        $count = 0;
        $total = $this->assetIds === null ? 0 : \count($this->assetIds);

        foreach ($this->assetIds as $assetId) {

            $this->setProgress($queue, $count / $total, Craft::t('app', '{step} of {total}', [
                'step' => $count + 1,
                'total' => $total,
            ]));

            $count++;
            
            $asset = Asset::find()
                ->id($assetId)
                ->siteId($this->siteId)
                ->one();

            if (!$asset || (bool) EscapeDam::getInstance()->files->getFileForImportedAsset($asset)) {
                continue;
            }

            // Get DAM files that could match this Asset
            $apiService = EscapeDam::getInstance()->api;
            $apiService->setUserId($this->userId);

            $result = $apiService->queryForOriginalDamFileByAsset($asset) ?? [];

            $matchingDamFiles = [];

            foreach ($result as $damFileData) {
                // Check the extension
                if (\strtolower((string) $damFileData['extension']) !== \strtolower($asset->getExtension())) {
                    continue;
                }
                // Compare the width and height
                $assetWidth = (int)$asset->getWidth();
                $assetHeight = (int)$asset->getHeight();
                $damFileDataWidth = (int)$damFileData['width'];
                $damFileDataHeight = (int)$damFileData['height'];
                if ($assetWidth !== $damFileDataWidth || $assetHeight !== $damFileDataHeight) {
                    // Compare aspect ratio
                    $damFileDataAspectRatio = $damFileData['width'] / $damFileData['height'];
                    $assetAspectRatio = $assetWidth / $assetHeight;
                    if ($damFileDataAspectRatio !== $assetAspectRatio) {
                        // Scale the DAM image to Asset's dimensions and compare again
                        $newDamWidth = $assetWidth;
                        $newDamHeight = (int)(round($newDamWidth / $damFileDataAspectRatio));
                        if ($newDamHeight !== $assetHeight) {
                            continue;
                        }
                    }
                }
                $matchingDamFiles[] = $damFileData;
            }

            if (\count($matchingDamFiles) !== 1) {
                continue;
            }

            $matchingDamFile = $matchingDamFiles[0];

            // Create new record
            $record = new ImportedFile();
            $record->damId = (int)$matchingDamFile['id'];
            $record->assetId = (int)$assetId;
            $record->settings = Json::encode($matchingDamFile);
            $record->dateSynced = Db::prepareDateForDb(new \DateTime());
            $record->createdByUtility = true;

            $transaction = Craft::$app->getDb()->beginTransaction();

            try {
                if (!$record->save()) {
                    throw new \Exception("Could not save imported file record for Asset {$assetId}.");
                }
                $transaction->commit();
            } catch (\Throwable $e) {
                $transaction->rollBack();
                throw $e;
            }

        }
        
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return 'Fixing missing imported DAM file records';
    }

}
