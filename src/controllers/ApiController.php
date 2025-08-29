<?php

namespace escape\escapedam\controllers;

use Craft;
use craft\db\Query;
use craft\elements\MatrixBlock;
use craft\helpers\ConfigHelper;
use craft\web\Controller;

use escape\escapedam\records\ImportedFile;

use yii\web\Response;

class ApiController extends Controller
{

    protected array|bool|int $allowAnonymous = ['file-usage'];

    /**
     * @return Response
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionFileUsage(): Response {
        $this->response->setNoCacheHeaders();
        $this->response->getHeaders()->set('X-Robots-Tag', 'none');

        $fileId = (int)$this->request->getRequiredParam('fileId');

        $usages = Craft::$app->getCache()->getOrSet([
            __METHOD__,
            $fileId,
        ], static function () use ($fileId) {
            $usages = [];

            try {
                $usagesQuery = (new Query())
                    ->select([
                        'importedfiles.id',
                        'importedfiles.uid',
                        'importedfiles.damId',
                        'importedfiles.assetId',
                        'importedfiles.sourceElementId',
                        'importedfiles.dateSynced',
                        'importedfiles.dateCreated',
                        'importedfiles.dateUpdated',
                        'fields.handle AS fieldHandle',
                        'fields.context AS fieldContext',
                        'assets.filename AS filename',
                    ])
                    ->from(ImportedFile::tableName() . ' AS importedfiles')
                    ->where(['damId' => $fileId])
                    ->andWhere(['!=', 'assetId', 'NULL'])
                    ->andWhere(['!=', 'sourceElementId', 'NULL'])
                    ->innerJoin('{{%fields}} AS fields', 'fields.id = importedfiles.fieldId')
                    ->innerJoin('{{%assets}} AS assets', 'assets.id = importedfiles.assetId');

                foreach ($usagesQuery->all() as $usage) {
                    $sourceElementId = $usage['sourceElementId'];
                    $element = Craft::$app->getElements()->getElementById($sourceElementId);
                    if ($element instanceof MatrixBlock) {
                        $element = $element->getOwner();
                    }
                    $usages[] = [
                        ...$usage,
                        'sourceElement' => [
                            'title' => $element->title,
                            'id' => $element->id,
                            'url' => $element->getUrl(),
                            'status' => $element->status,
                        ],
                    ];
                }
            } catch (\Throwable $e) {
                Craft::error($e, __METHOD__);
                return false;
            }

            return $usages;
        }, ConfigHelper::durationInSeconds('PT1M'));

        return $this->asJson($usages);
    }

}
