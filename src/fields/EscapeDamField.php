<?php

namespace escape\escapedam\fields;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Volume;
use craft\elements\Asset;
use craft\errors\InvalidSubpathException;
use craft\fields\Assets;
use craft\helpers\Assets as AssetsHelper;
use craft\helpers\FileHelper;

use escape\escapedam\EscapeDam;

use yii\base\InvalidConfigException;

class EscapeDamField extends Assets
{


    /** @inheritdoc */
    public bool $allowUploads = false;

    /** @inheritdoc */
    public ?string $viewMode = 'large';

    /**
     * @var string|null Where files should be restricted to, in format
     * "folder:X", where X is the craft\models\VolumeFolder ID
     */
    public $damImportLocationSource;

    /**
     * @var string|null The subpath that files should be restricted to
     */
    public $damImportLocationSubpath;

    /**
     * @var string|null The label for the DAM selection input button
     */
    public $damSelectionLabel;

    /**
     * @var bool If selecting and uploading via native Assets should be allowed
     */
    public $enableAssetsInput = true;

    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        if ((\debug_backtrace()[1]['function'] ?? null) === 'twig_get_attribute') {
            return Craft::t('app', 'Assets');
        }
        return Craft::t('app', 'Escape DAM');
    }

    public static function defaultDamSelectionLabel(): string
    {
        return Craft::t('escapedam', 'Import from DAM');
    }

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        $this->settingsTemplate = 'escapedam/_components/fields/EscapeDamField_settings';
        $this->inputTemplate = 'escapedam/_components/fields/EscapeDamField_input';
        $this->inputJsClass = 'Craft.EscapeDam.DamSelectInput';

        $this->damImportLocationSource = $this->_folderSourceToVolumeSource($this->damImportLocationSource);

        // Make sure that JSON is an allowed filekind if videos are an allowed filekind, because videos are imported as JSON files (similar to how the Embedded Assets plugin works)
        if ($this->allowedKinds && \in_array(Asset::KIND_VIDEO, $this->allowedKinds) && !\in_array(Asset::KIND_JSON, $this->allowedKinds)) {
            $this->allowedKinds[] = Asset::KIND_JSON;
        }
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        $this->damImportLocationSource = $this->_volumeSourceToFolderSource($this->damImportLocationSource);
        return parent::getSettingsHtml();
    }

    /**
     * Resolve source path for DAM importing for this field.
     *
     * @param ElementInterface|null $element
     * @throws InvalidSubpathException
     */
    public function resolveDynamicPathToImportFolderId(ElementInterface $element = null): int
    {
        return $this->_determineImportFolderId($element, true);
    }

    /**
     * @inheritdoc
     */
    protected function inputTemplateVariables($value = null, ElementInterface $element = null): array
    {
        $variables = parent::inputTemplateVariables($value, $element);
        $allowedKinds = $this->allowedKinds ?: [Asset::KIND_IMAGE];
        $allKinds = AssetsHelper::getAllowedFileKinds();
        $allowedExtensions = \array_reduce(\array_keys($allKinds), function(array $carry, string $key) use ($allKinds, $allowedKinds) {
            if (!\in_array($key, $allowedKinds)) {
                return $carry;
            }
            return \array_merge($carry, $allKinds[$key]['extensions']);
        }, []);
        $allowedExtensions = \array_values(\array_unique($allowedExtensions));
        return \array_merge($variables, [
            'enableAssetsInput' => $this->enableAssetsInput,
            'damSelectionLabel' => $this->damSelectionLabel ?: self::defaultDamSelectionLabel(),
            'allowedExtensions' => $allowedExtensions,
        ]);
    }

    /**
     * Convert a folder:UID source key to a volume:UID source key.
     *
     * @param mixed $sourceKey
     */
    private function _folderSourceToVolumeSource($sourceKey): string
    {
        if ($sourceKey && is_string($sourceKey) && str_starts_with($sourceKey, 'folder:')) {
            $parts = explode(':', $sourceKey);
            $folder = Craft::$app->getAssets()->getFolderByUid($parts[1]);

            if ($folder) {
                try {
                    /** @var Volume $volume */
                    $volume = $folder->getVolume();
                    return 'volume:' . $volume->uid;
                } catch (InvalidConfigException) {
                    // The volume is probably soft-deleted. Just pretend the folder didn't exist.
                }
            }
        }

        return (string)$sourceKey;
    }

    /**
     * Convert a volume:UID source key to a folder:UID source key.
     *
     * @param mixed $sourceKey
     */
    private function _volumeSourceToFolderSource($sourceKey): string
    {
        if ($sourceKey && is_string($sourceKey) && str_starts_with($sourceKey, 'volume:')) {
            $parts = explode(':', $sourceKey);
            /** @var Volume|null $volume */
            $volume = Craft::$app->getVolumes()->getVolumeByUid($parts[1]);
            if ($volume && $folder = Craft::$app->getAssets()->getRootFolderByVolumeId($volume->id)) {
                return 'folder:' . $folder->uid;
            }
        }
        return (string)$sourceKey;
    }

    /**
     * Determine an upload folder id by looking at the settings and whether Element this field belongs to is new or not.
     *
     * @param ElementInterface|null $element
     * @param bool $createDynamicFolders whether missing folders should be created in the process
     * @throws InvalidSubpathException if the folder subpath is not valid
     * @throws InvalidVolumeException if there's a problem with the field's volume configuration
     */
    private function _determineImportFolderId(ElementInterface $element = null, bool $createDynamicFolders = true): int
    {
        /** @var Element $element */
        $importVolume = $this->damImportLocationSource;
        $subpath = $this->damImportLocationSubpath;
        if (!$subpath || !\strlen($subpath)) {
            $subpath = \strtolower($this->handle) . '-' . $this->id . '/' . date('Ymdhis');
        }
        $settingName = Craft::t('escapedam', 'Import Location');
        $assets = Craft::$app->getAssets();
        try {
            if (!$importVolume) {
                throw new InvalidVolumeException();
            }
            $folderId = $this->_resolveVolumePathToFolderId($importVolume, $subpath, $element, $createDynamicFolders);
        } catch (InvalidVolumeException $e) {
            throw new InvalidVolumeException(Craft::t('app', 'The {field} field’s {setting} setting is set to an invalid volume.', [
                'field' => $this->name,
                'setting' => $settingName,
            ]), 0, $e);
        } catch (InvalidSubpathException $e) {
            // If this is a new/disabled element, the subpath probably just contained a token that returned null, like {id}
            // so use the user's upload folder instead
            if ($element === null || !$element->getId() || !$element->enabled || !$createDynamicFolders) {
                $userFolder = $assets->getUserTemporaryUploadFolder();
                $folderId = $userFolder->id;
            } else {
                // Existing element, so this is just a bad subpath
                throw new InvalidSubpathException($e->subpath, Craft::t('app', 'The {field} field’s {setting} setting has an invalid subpath (“{subpath}”).', [
                    'field' => $this->name,
                    'setting' => $settingName,
                    'subpath' => $e->subpath,
                ]), 0, $e);
            }
        }
        return $folderId;
    }

    /**
     * Resolve a source path to it's folder ID by the source path and the matched source beginning.
     *
     * @param ElementInterface|null $element
     * @param bool $createDynamicFolders whether missing folders should be created in the process
     * @throws InvalidSubpathException if the subpath cannot be parsed in full
     * @throws InvalidVolumeException if the volume root folder doesn’t exist
     */
    private function _resolveVolumePathToFolderId(string $uploadSource, string $subpath, ElementInterface $element = null, bool $createDynamicFolders = true): int
    {
        $assetsService = Craft::$app->getAssets();
        $volumeId = $this->_volumeIdBySourceKey($uploadSource);
        // Make sure the volume and root folder actually exists
        if ($volumeId === null || ($rootFolder = $assetsService->getRootFolderByVolumeId($volumeId)) === null) {
            throw new InvalidVolumeException();
        }
        // Are we looking for a subfolder?
        $subpath = is_string($subpath) ? trim($subpath, '/') : '';
        if ($subpath === '') {
            // Get the root folder in the source
            $folderId = $rootFolder->id;
        } else {
            // Prepare the path by parsing tokens and normalizing slashes.
            try {
                $renderedSubpath = Craft::$app->getView()->renderObjectTemplate($subpath, $element);
            } catch (\Throwable $e) {
                throw new InvalidSubpathException($subpath, null, 0, $e);
            }
            // Did any of the tokens return null?
            if (
                $renderedSubpath === '' ||
                trim((string)$renderedSubpath, '/') != $renderedSubpath ||
                str_contains((string)$renderedSubpath, '//')
            ) {
                throw new InvalidSubpathException($subpath);
            }
            // Sanitize the subpath
            $segments = explode('/', (string)$renderedSubpath);
            foreach ($segments as &$segment) {
                $segment = FileHelper::sanitizeFilename($segment, [
                    'asciiOnly' => Craft::$app->getConfig()->getGeneral()->convertFilenamesToAscii
                ]);
            }
            unset($segment);
            $subpath = implode('/', $segments);
            $folder = $assetsService->findFolder([
                'volumeId' => $volumeId,
                'path' => $subpath . '/'
            ]);
            // Ensure that the folder exists
            if (!$folder) {
                if (!$createDynamicFolders) {
                    throw new InvalidSubpathException($subpath);
                }
                $volume = Craft::$app->getVolumes()->getVolumeById($volumeId);
                $folderId = $assetsService->ensureFolderByFullPathAndVolume($subpath, $volume)->id;
            } else {
                $folderId = $folder->id;
            }
        }
        return $folderId;
    }
}
