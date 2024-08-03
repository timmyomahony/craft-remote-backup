# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 5.0.0 - 2024-08-03

### Added

- Craft 5 support.

### Fixed

- Fixed file listing issue with AWS. See Remote Backup [Issue #54](https://github.com/timmyomahony/craft-remote-backup/issues/54) and Remote Core [PR #24](https://github.com/timmyomahony/craft-remote-core/pull/24)
- Cleaned up logging. See Remote Backup [Issue #59](https://github.com/timmyomahony/craft-remote-backup/issues/59) and Remote Core [PR #25](https://github.com/timmyomahony/craft-remote-core/pull/25)

## 4.1.6 - 2023-12-07

### Added

- Merged support for IAM profiles. See [Issue #44](https://github.com/timmyomahony/craft-remote-backup/issues/44).

### Fixed

- Simplified the regex to accept all characters in the system name & env. Hopefully avoiding further interface errors. See [Issue #49](https://github.com/timmyomahony/craft-remote-backup/issues/49)

## 4.1.5 - 2023-10-26

### Changed

- Updated branding! Shiny new icon that better suits the Craft CMS aesthetic, as well as [a new documentation website](https://craft-plugins.timmyomahony.com/remote-backup).

## 4.1.4 - 2023-09-29

### Changed

- Bumped remote core version

### Fixed

- Issue with volume interface button not disabling

## 4.1.3 - 2022-11-07

### Fixed

- [Issue #51](https://github.com/weareferal/craft-remote-sync/issues/51)
- [Issue #52](https://github.com/weareferal/craft-remote-sync/issues/52)

## 4.1.2 - 2022-10-8

### Fixed

- [Issue #40](https://github.com/weareferal/craft-remote-backup/issues/40)

## 4.1.0 - 2022-10-5

### Added

- Temporary files are now deleted more reliably now when something goes wrong, avoiding issues with disk space getting eaten up.
- Added a proper table layout to the utilties interface to improve readability.
- Added "file size" to the utilities interface so you can now see how larger your files are.
- Added timezone handling to files to give accurate dates, times & "time since"
- Added a "test connection" button to the settings
- Added file chunking to Google Drive upload
- Added small icon to the utilities section to show current cloud provider at-a-glance

### Changed

- Changed the filename formatting to use double underscores, improving reliability.
- Refactored core module aliases for consistancy
- Refactored the base provider service, improving logging.

### Fixed

- [Issue #10](https://github.com/weareferal/craft-remote-backup/issues/10)
- [Issue #11](https://github.com/weareferal/craft-remote-core/pull/11) (thanks @joelzerner)
- [Issue #36](https://github.com/weareferal/craft-remote-backup/issues/36)
- [Issue #43](https://github.com/weareferal/craft-remote-sync/issues/43)
- [Issue #45](https://github.com/weareferal/craft-remote-sync/issues/45)
- [Issue #34](https://github.com/weareferal/craft-remote-backup/issues/34)

### Removed

- Removed Dropbox temporarily due to issues with long-held tokens.

## 4.0.1 - 2022-08-19

### Fixed

- Added fix for issue #48 with permissions

## 4.0.0 - 2022-08-18

### Added

- Craft 4 compatibility. Version has jumped from 1.X.X to 4.X.X to make following Craft easier.

## 1.4.0 - 2020-12-08

### Added

- Added support for remote volumes
- Added TTR to queue jobs (issue #38 on craft-remote-sync)
- Added time and duration to console command output

### Changed

- Bumped version number for parity between sync & backup plugins
- Updated readme to call-out cron requirement
- Fixed filename regex (issue #26 on craft-remote-sync)
- Moved shared utilities JS and CSS to core module
- Added show/hide display logic to utilities (issue #7)
- Updated the formatting for file table (issue #10 on craft-remote-backup)

## 1.2.2 - 2020-11-06

### Changed

- Updated core library version

## 1.2.1 - 2020-07-14

### Changed

- Made Backblaze B2 settings labels clearer

## 1.2.0 - 2020-07-12

### Changed

- Moved shared code into a separate `craft-remote-core` package dependency to be shared between `craft-remote-sync` and `craft-remote-backup`

### Added

- Added Digital Ocean Spaces provider & documentation
- Updated Backblaze provider to use existing AWS API (you will need to update your settings if using the old Backblaze provider)

## 1.1.0 - 2020-05-18

### Added

- Dropbox provider & documentation
- Google Drive provider & documentation
- Backblaze provider & documentation
- Interface improvement: show a "latest" tag
- Interface improvement: show the "time since" when hovering over backups

## 1.0.1 - 2020-03-06

### Added

- Added ability to hide either the database or volume backups from the utilities panel

## 1.0.0 - 2020-03-06

### Added

- Initial release
