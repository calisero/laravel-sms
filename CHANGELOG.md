# Changelog

All notable changes to `calisero/laravel-sms` will be documented in this file.

## [Unreleased]

### Added
- Initial release
- Laravel 12.x support
- SMS sending via Facade, Notification channel, and direct client
- Webhook handling with signature verification
- Validation rules for phone numbers (E.164) and sender IDs
- Artisan commands for testing and webhook verification
- Comprehensive test suite with Orchestra Testbench
- GitHub Actions CI pipeline
- PHPStan level 8 static analysis
- PHP CS Fixer code formatting

### Features
- **Service Container Integration**: Automatic binding and registration
- **Queue Support**: Full support for Laravel's queue system
- **Event System**: Webhook events for message delivery status
- **Logging**: Configurable logging with request/response details
- **Error Handling**: Proper exception handling and mapping
- **Configuration**: Publishable config with environment variable support

### Requirements
- PHP 8.2 - 8.4
- Laravel 12.x
- Calisero PHP SDK ^2.0
