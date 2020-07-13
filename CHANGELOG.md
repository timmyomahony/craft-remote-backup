# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 1.0.0 - 2020-03-06

### Added

- Initial release

## 1.0.1 - 2020-03-06

### Added

- Added ability to hide either the database or volume backups from the utilities panel

## 1.1.0 - 2020-05-18

### Added

- Dropbox provider & documentation
- Google Drive provider & documentation
- Backblaze provider & documentation
- Interface improvement: show a "latest" tag
- Interface improvement: show the "time since" when hovering over backups

## 1.2.0 - 2020-07-12

### Changed

- Moved shared code into a separate `craft-remote-core` package dependency to be shared between `craft-remote-sync` and `craft-remote-backup`

### Added

- Added Digital Ocean Spaces provider & documentation
- Updated Backblaze provider to use existing AWS API (you will need to update your settings if using the old Backblaze provider)
