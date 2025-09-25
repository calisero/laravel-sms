# Changelog

All notable changes to `calisero/laravel-sms` will be documented in this file.

## [Unreleased]
- (no changes yet)

## [1.0.4] - 2025-09-25
### Documentation
- Added comprehensive environment variables reference table in README
- Enhanced installation and configuration documentation with complete variable listing
- Improved README structure with clearer sections for all configuration options
- Added detailed descriptions for all available environment variables
- Organized configuration variables by category (Required, Connection, Webhook, Monitoring, Logging)

### Added
- Complete environment variables documentation in README.md
- Configuration reference table with descriptions and default values
- Enhanced setup instructions with all available options

### Improved
- Better organization of configuration documentation
- Clearer categorization of environment variables
- More comprehensive setup guide for new users

## [1.0.3] - 2025-09-25
### Added
- Full PHP 8.4 compatibility alongside existing PHP 8.2 and 8.3 support
- Enhanced memory management for PHP CS Fixer operations
- Improved CI/CD pipeline with comprehensive PHP version matrix testing

### Changed
- Updated PHP version constraint to `^8.2` (supports 8.2, 8.3, and 8.4)
- Optimized PHP CS Fixer configuration with proper memory allocation
- Enhanced GitHub Actions workflow to test against PHP 8.2, 8.3, and 8.4
- Improved webhook token validation test coverage and error messages
- Updated composer scripts for better performance and reliability

### Fixed
- Resolved PHP CS Fixer memory exhaustion issues on larger codebases
- Fixed webhook token validation test assertions to match actual error responses
- Cleaned up empty test files that were causing PHPUnit warnings
- Improved code formatting consistency across all source files

### Technical
- Updated `composer.json` to support latest PHP versions while maintaining Laravel 12.x compatibility
- Enhanced CI pipeline stability with proper dependency version constraints
- Improved development workflow with optimized quality assurance scripts
- Added proper timeout handling for long-running CS Fixer operations

### Requirements
- PHP 8.2, 8.3, or 8.4
- Laravel 12.x
- Calisero PHP SDK ^2.0

## [1.0.2] - 2025-09-25
### Added
- Optional webhook token authentication: set `CALISERO_WEBHOOK_TOKEN=your-secret` (config: `calisero.webhook.token`) to require a matching `?token=your-secret` query parameter on the webhook route. Middleware `ValidateWebhookToken` is automatically applied only when a token is configured, preserving previous unauthenticated behavior when absent.

### Notes
- Enables lightweight shared-secret protection without reintroducing the removed HMAC signature scheme.

## [1.0.1] - 2025-09-25
### Removed
- Signature-based webhook verification (middleware & HMAC secret) – webhook endpoint is now unauthenticated by default.
- `calisero:webhook:verify` artisan command.
- Webhook secret / verify configuration keys.

### Added
- Automatic `callback_url` injection when `CALISERO_WEBHOOK_ENABLED=true` and no explicit callback provided.
- Credit monitoring events: `CreditLow`, `CreditCritical` with configurable thresholds.
- `MessageSent` event (lifecycle expansion: sent → delivered/failed).
- Multilingual validation messages (English & Romanian) – publishable via `calisero-translations` tag.
- Configurable webhook enabling flag (`CALISERO_WEBHOOK_ENABLED`).

### Changed
- Webhook routes now register only when explicitly enabled (no secret required).
- `SmsClient` sends structured DTO requests and supports snake_case / camelCase optional parameters.
- Updated README & examples to reflect removal of signature verification and new behaviors.
- Improved type declarations for `SmsClient` contract & implementation.

### Fixed
- Normalized parameter naming (`schedule_at`, `callback_url`) in notification channel.
- Removed stale comments and deprecated code blocks prior to publication.

### Notes
- This release includes a breaking change if you depended on prior signature verification; add your own middleware (token/IP allow‑list) if needed.

## [1.0.0] - 2025-09-??
### Added
- Initial release
- Laravel 12.x support
- SMS sending via Facade, Notification channel, and direct client
- Webhook handling (basic, unauthenticated in current revision)
- Validation rules for phone numbers (E.164) and sender IDs
- Artisan command for test SMS sending
- Comprehensive test suite with Orchestra Testbench
- GitHub Actions CI pipeline
- PHPStan static analysis & PHP CS Fixer formatting
