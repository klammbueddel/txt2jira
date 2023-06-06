# Changelog
All notable changes to this project will be documented in this file.

## [2.1.1] - 2023-06-06
### Changed
- Delete only last node 
- Remove +++ from date 
- Update description of arguments 
### Added
- Allow to add time with + 
- Document key benefits 
### Fixed
- Hide complete response on authorization error 
- Cache only HTTP 400 errors 
- Change current comment if task is running 
- Fix typo in doc 

## [2.1.0] - 2022-12-11
### Changed
- The `start` command was renamed to `log` command. The `stop` command was removed.
- Change order of suggestions to show last recent at bottom.
- The `delete` command now deletes the end time before removing the whole log. This is useful to continue the work on the current task.
### Fixed
- Move logs to other days if changed time crosses day border.
- Use comment of last issue when started via alias.
### Added
- Support to add start `<time>` to `log` command
- Support to pass `<duration>` to `log` command in Jira format
- Support to show all logs uncombined (`list -a`)
- Add `clear-cache` command to clear the cache.
- Offline mode (`--offline` flag) to disable summary resolving on demand.
- Use of alternative config file (`--file` option).

## [2.0.0] - 2022-12-04
### Updated
- Complete rewrite of parser and interpreter semantics
### Added
- Added various commands to interact with the log file on the command line
- Resolve issue summary from Jira

## [1.0.5] - 2022-11-22
### Fixed
- Ignore logs with invalid date - thx to [@deeaitsch84](https://github.com/deeaitsch84) for feedback

## [1.0.4] - 2022-11-03
### Fixed
- Fixed initial setup

## [1.0.3] - 2022-10-21
### Added
- Handle exception during export - thx to [@deeaitsch84](https://github.com/deeaitsch84) for feedback
### Fixed
- Persist configuration after successful verification

## [1.0.2] - 2022-10-20
### Fixed
- Fix edit option of init command

## [1.0.1] - 2022-10-17
### Added
- Verify configuration - thx to [@deeaitsch84](https://github.com/deeaitsch84) for feedback

## [1.0.0]

- Initial release
