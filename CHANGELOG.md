# Escape DAM Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 2.2.0 - 2023-05-17
### Added 
- Adds Craft 4.4 compatibility
### Changed 
- Escape DAM now requires Craft 4.4 or later
- It's no longer possible to select volumes in the `allowedVolumesForImport` settings as "Native Assets" sources
- Field subpaths now get the field handle+ID and a datestamp... subpath. A sub path for the sub path yes

## 2.1.0 - 2023-02-14
### Added
- Adds a "VIDEO" tag to DAM video thumbnails
### Fixed
- Cleaned up some exception handing in the DAM fieldtype

## 2.0.3 - 2023-02-11
### Fixed
- Fixes an "Element query executed before Craft is fully initialized" warning   

## 2.0.2 - 2023-02-07
### Fixed
- Fixed some typing issues

## 2.0.1 - 2023-02-07
### Fixed
- Fixed some bugs related to import source paths  

## 2.0.0 - 2023-02-07
### Added
- Added compatibility with Craft 4.3+  
- Added "Plugin Name" config setting  
- Added the `allowedVolumesForImport` config setting, to reduce the risk of files being imported to undesired volumes  
### Changed
- DAM fields' "Enable native assets" field setting is now enabled by default for new fields
- DAM fields' "View Mode" field setting is now set to "Large Thumbnails" by default for new fields
- Upgraded the `firebase/php-jwt` package to v6

## 1.6.0 - 2022-11-09
### Changed
- Use MuxPlayer for video previews
### Fixed
- Fixes an issue where the storage key would be invalid if the DAM field was in a tab

## 1.5.7 - 2022-07-04
### Fixed 
- Adds a missing use statement that could cause an exception if a file could not be downloaded  

## 1.5.6 - 2022-04-27
### Added  
- Added the `hlsVideoLazyloadEvent` config setting

## 1.5.5 - 2022-03-28
### Added
- Added the `hlsVideoLazyloadDelay` config setting

## 1.5.4 - 2022-03-19
### Changed
- DAM video tags and the Hls.js polyfill script now default to being lazyloaded

## 1.5.3 - 2022-03-02
### Fixed
- Fixes an issue where it wasn't possible to pass an `id` attribute to DAM video tags  

## 1.5.2 - 2022-01-31
### Fixed
- Fixes a PHP 8.0 compatability issue

## 1.5.1 - 2022-01-31
### Fixed
- Fixes issues with typed properties in Dam Field class

## 1.5.0 - 2022-01-31
### Added
- Adds support for Mux videos

## 1.4.0 - 2020-10-19

### Fixed  
- Fixed compatibility with the changes to native Assets fields in Craft 3.5.13  

### Changed  
- Escape DAM now requires Craft 3.5.13 or later  

## 1.3.0 - 2020-09-25

### Added
- Added utility for repairing missing imported record relations  
- Added "DAM Link" field  

### Changed
- Refactored `Files::relateImportedAssetToElement()`  
- Adds `createdByUtility` column to the `{{%escapedam_importedfiles}}` table  

## 1.2.2 - 2020-09-25

### Added  
- Adds the Escape DAM utility (nothing in it yet though)

### Changed
- Imported files are now downloaded directly from S3, not Imgix  

## 1.2.1 - 2020-09-22

### Added
- Adds `Api::setUser()` method to enable bearer tokens in anonymous console requests  

### Improved
- The `uploaderId` attribute is now set for imported Assets  

## 1.2.0 - 2020-09-22

### Added
- Adds `Files::importFile()` method to facilitate Export/Import integration

### Fixed
- Fixes a bug where imported records would get deleted if related elements were deleted (changes FK restrictions for the `escapedam_importedfiles` table)  

### Changed  
- Custom focal point functionality is removed (use Focal Point Me! instead)

## 1.1.0 - 2020-09-20

## Added  
- Added `Files::getFileForImportedAsset()` method  

## Improved  
- Improves Craft 3.5 compatibility  

## Changed  
- Escape DAM now requires Craft 3.5 or higher

## 1.0.2 - 2020-05-18

### Improved
- Improves Craft 3.4 compatibility

### Changed
- Escape DAM now requires Craft 3.4 or higher

## 1.0.1 - 2019-10-23

### Fixed  
- Fixes an issue with expired tokens  

## 1.0.0 - 2019-05-25

### Added  
- Initial release  
