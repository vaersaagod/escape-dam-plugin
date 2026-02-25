# Escape DAM Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 3.2.1 - 2026-02-25
### Changed
- Removed `"minimum-stability": "dev"` from `composer.json`
- Removed `composer.lock` from repo

## 3.2.0 - 2026-02-25
### Changed
- Bumped the requirement for `firebase/php-jwt` to `^7.0`

## 3.1.1 - 2026-02-17
### Fixed
- Fixed a bug w/ multisite installs on 5.9+

## 3.1.0 - 2026-02-16
### Added
- Added the `escapedam/api/file-usage` endpoint
- Fixed Craft 5.9 compatibility issues 
### Changed
- For very large source images, a 2500px transform will be downloaded instead of the original when importing files from the DAM
- Escape DAM now uses its own log file
- Elements' canonical IDs are now recorded in the `sourceElementId` column for the `escapedam_importedfiles` database table
- Escape DAM fields no longer checks for previously imported assets on import (a new asset is created for every import)
- Removed the "Replace" action from DAM fields (the native action doesn't work and isn't worth fixing)
### Fixed
- Fixed a bug where the selected "Import Location" wasn't selected in field setting forms
- Fixed a bug where assets in DAM fields wouldn't display their full action menu after upload

## 3.0.0 - 2024-08-05
### Added
- Craft 5 support
