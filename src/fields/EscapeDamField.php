<?php


namespace escape\escapedam\fields;


use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Volume;
use craft\elements\db\ElementQuery;
use craft\fields\Assets;
use craft\helpers\Template;
use craft\helpers\FileHelper;
use yii\base\InvalidConfigException;

class EscapeDamField extends Assets
{

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
     * @var bool|null If selecting and uploading via standard Assets should be allowed
     */
    public $enableAssetsInput;

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

    /**
     * @return string
     */
    public static function defaultSelectionLabel(): string
    {
        return Craft::t('escapedam', 'Select from Assets');
    }

    /**
     * @return string
     */
    public static function defaultDamSelectionLabel(): string
    {
        return Craft::t('escapedam', 'Select from DAM');
    }

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->damImportLocationSource = $this->_folderSourceToVolumeSource($this->damImportLocationSource);
        $this->settingsTemplate = 'escapedam/_components/fields/EscapeDamField_settings';
        $this->inputTemplate = 'escapedam/_components/fields/EscapeDamField_input';
        $this->inputJsClass = 'Craft.EscapeDam.DamSelectInput';
    }

    /**
     * Resolve source path for DAM importing for this field.
     *
     * @param ElementInterface|null $element
     * @return int
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
        return \array_merge($variables, [
            'enableAssetsInput' => $this->enableAssetsInput,
            'damSelectionLabel' => $this->damSelectionLabel ?: self::defaultDamSelectionLabel(),
        ]);
    }

    /**
     * Convert a folder:UID source key to a volume:UID source key.
     *
     * @param mixed $sourceKey
     * @return string
     */
    private function _folderSourceToVolumeSource($sourceKey): string
    {
        if ($sourceKey && is_string($sourceKey) && strpos($sourceKey, 'folder:') === 0) {
            $parts = explode(':', $sourceKey);
            $folder = Craft::$app->getAssets()->getFolderByUid($parts[1]);

            if ($folder) {
                try {
                    /** @var Volume $volume */
                    $volume = $folder->getVolume();
                    return 'volume:' . $volume->uid;
                } catch (InvalidConfigException $e) {
                    // The volume is probably soft-deleted. Just pretend the folder didn't exist.
                }
            }
        }

        return (string)$sourceKey;
    }

    /**
     * Determine an upload folder id by looking at the settings and whether Element this field belongs to is new or not.
     *
     * @param ElementInterface|null $element
     * @param bool $createDynamicFolders whether missing folders should be created in the process
     * @return int
     * @throws InvalidSubpathException if the folder subpath is not valid
     * @throws InvalidVolumeException if there's a problem with the field's volume configuration
     */
    private function _determineImportFolderId(ElementInterface $element = null, bool $createDynamicFolders = true): int
    {
        /** @var Element $element */
        $importVolume = $this->damImportLocationSource;
        $subpath = $this->damImportLocationSubpath;
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
            if ($element === null || !$element->id || !$element->enabled || !$createDynamicFolders) {
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
     * @param string $uploadSource
     * @param string $subpath
     * @param ElementInterface|null $element
     * @param bool $createDynamicFolders whether missing folders should be created in the process
     * @return int
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
                trim($renderedSubpath, '/') != $renderedSubpath ||
                strpos($renderedSubpath, '//') !== false
            ) {
                throw new InvalidSubpathException($subpath);
            }
            // Sanitize the subpath
            $segments = explode('/', $renderedSubpath);
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
                $folderId = $assetsService->ensureFolderByFullPathAndVolume($subpath, $volume);
            } else {
                $folderId = $folder->id;
            }
        }
        return $folderId;
    }
}
