<?php

namespace escape\escapedam\fields;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\errors\InvalidSubpathException;
use craft\errors\InvalidVolumeException;
use craft\errors\VolumeException;
use craft\fields\Assets;
use craft\helpers\Assets as AssetsHelper;

use craft\models\VolumeFolder;

class EscapeDamField extends Assets
{

    /** @inheritdoc */
    public $allowUploads = false;

    /**
     * @var string|null Where files should be restricted to, in format
     * "folder:X", where X is the craft\models\VolumeFolder ID
     */
    public ?string $damImportLocationSource = null;

    /**
     * @var string|null The subpath that files should be restricted to
     */
    public ?string $damImportLocationSubpath = null;

    /**
     * @var string|null The label for the DAM selection input button
     */
    public ?string $damSelectionLabel = null;

    /**
     * @var bool If selecting and uploading via standard Assets should be allowed
     */
    public bool $enableAssetsInput = false;

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
     * @param ElementInterface|null $element
     * @return int
     * @throws InvalidSubpathException
     * @throws InvalidVolumeException
     * @throws VolumeException
     * @throws \ReflectionException
     */
    public function getImportFolderId(ElementInterface $element = null): int
    {
        return $this->_importFolder($element)->id;
    }

    /**
     * @inheritdoc
     */
    protected function inputTemplateVariables($value = null, ElementInterface $element = null): array
    {
        $variables = parent::inputTemplateVariables($value, $element);
        $allowedKinds = $this->allowedKinds ?: [Asset::KIND_IMAGE];
        $allKinds = AssetsHelper::getAllowedFileKinds();
        $allowedExtensions = \array_reduce(\array_keys($allKinds), function (array $carry, string $key) use ($allKinds, $allowedKinds) {
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
     * Determine an upload folder id by looking at the settings and whether Element this field belongs to is new or not.
     *
     * @param ElementInterface|null $element
     * @return VolumeFolder
     * @throws InvalidSubpathException
     * @throws InvalidVolumeException
     * @throws VolumeException
     */
    private function _importFolder(ElementInterface $element = null): VolumeFolder
    {
        /** @var Element $element */
        $sourceKey = $this->damImportLocationSource;
        $subpath = $this->damImportLocationSubpath;
        if (!$subpath || !strlen($subpath)) {
            $subpath = strtolower($this->handle) . '-' . $this->id . '/' . date('Ymdhis');
        }
        $settingName = Craft::t('escapedam', 'Import Location');
        $assets = Craft::$app->getAssets();
        try {
            if (!$sourceKey || !$folder = $this->_findFolder($sourceKey, $subpath, $element, true)) {
                throw new InvalidVolumeException();
            }
        } catch (InvalidVolumeException $e) {
            throw new InvalidVolumeException(Craft::t('app', 'The {field} field’s {setting} setting is set to an invalid volume.', [
                'field' => $this->name,
                'setting' => $settingName,
            ]), 0, $e);
        } catch (\InvalidSubpathException $e) {
            // If this is a new/disabled element, the subpath probably just contained a token that returned null, like {id}
            // so use the user's upload folder instead
            if ($element === null || !$element->id || !$element->enabled) {
                $folder = $assets->getUserTemporaryUploadFolder();
            } else {
                // Existing element, so this is just a bad subpath
                throw new InvalidSubpathException($e->subpath, Craft::t('app', 'The {field} field’s {setting} setting has an invalid subpath (“{subpath}”).', [
                    'field' => $this->name,
                    'setting' => $settingName,
                    'subpath' => $e->subpath ?? '',
                ]), 0, $e);
            }
        }
        return $folder;
    }

    /**
     * Convert a folder:UID source key to a volume:UID source key.
     *
     * @param $sourceKey
     * @return string
     * @throws \ReflectionException
     */
    private function _folderSourceToVolumeSource($sourceKey): string
    {
        $method = new \ReflectionMethod(parent::class, '_folderSourceToVolumeSource');
        $method->setAccessible(true);
        return $method->invokeArgs($this, [$sourceKey]);
    }

    /**
     * Convert a volume:UID source key to a folder:UID source key.
     *
     * @param $sourceKey
     * @return string
     * @throws \ReflectionException
     */
    private function _volumeSourceToFolderSource($sourceKey): string
    {
        $method = new \ReflectionMethod(parent::class, '_volumeSourceToFolderSource');
        $method->setAccessible(true);
        return $method->invokeArgs($this, [$sourceKey]);
    }

    /**
     * Finds a volume folder by a source key and dynamic subpath
     *
     * @param string $sourceKey
     * @param string $subpath
     * @param ElementInterface|null $element
     * @param bool $createDynamicFolders
     * @return VolumeFolder
     * @throws \ReflectionException
     */
    private function _findFolder(string $sourceKey, string $subpath, ElementInterface $element = null, bool $createDynamicFolders = true): VolumeFolder
    {
        $method = new \ReflectionMethod(parent::class, '_findFolder');
        $method->setAccessible(true);
        return $method->invokeArgs($this, [$sourceKey, $subpath, $element, $createDynamicFolders]);
    }

}
