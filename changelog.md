# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased]

## v4.1.1
### Fixed
- Fixed potential performance issue

## v4.1.0
### Changed
- Way easier method to support field enclosure

## v4.0.2
### Added
- Support for deleting export file after download

## v4.0.1
### Added
- Add support for LazyCollections

## v4.0
### Added
- Add support for Laravel 8

## v3.1
### Added
- Add support for empty values in field definition

## v3.0
### Changed
- Tiny but breaking changes (See upgrade hints in README.md)
- Added return types to interfaces
- New Namespace for the project is RealMediaTechnicStaudacher\LaravelFlatfiles
- Changed signature of the field callback method to prevent this: `function ($null, Asset $asset)`

## v2.0.1
### Fixed
- Fixed issue with local disks

## v2.0
### Changed
- Removed support for absolut local filepaths. You now always need a laravel disk

## v1.1
### Added
- Support for Laravel 7

## v1.0
### Added
- Support for Laravel 6

## v0.4.1
### Added
- #11 SYLK file format error

## v0.4
### Changed
- Now requireing Laravel v5.8

## v0.3
### Added
- ```return $flatfile->downloadResponse();```

### Fixed
- Problem with autoinjection directly in controllers

## v0.2
### Added
- Support mode to force enclose all columns

### Added
- Support to add Collection to addRow() to be used in custom queries

### Changed
- Callbacks now also get the complete "row"-model to process cross-column informations: `function ($value, $model) {}`

[Unreleased]: https://github.com/real-media-technic-staudacher/laravel-flatfiles/commits/master
