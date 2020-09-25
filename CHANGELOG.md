# Escape DAM Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

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
