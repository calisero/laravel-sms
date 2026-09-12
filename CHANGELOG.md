# Changelog

All notable changes to `calisero/laravel-sms` will be documented in this file.

## [Unreleased]

## [1.2.0] - 2026-09-12

### Added
- **Laravel 13 support** - the package now works on both Laravel 12.x and 13.x
  - `illuminate/support`, `illuminate/notifications`, `illuminate/validation`, `illuminate/routing`
    and `illuminate/console` constraints widened to `^12.0 || ^13.0`
- **PHP 8.5 support** - `php` constraint widened to `^8.2 || ^8.3 || ^8.4 || ^8.5`, and PHP 8.5
  added to every CI job
- **Laravel version matrix in CI** - static analysis and tests now run against both Laravel 12 and 13
  on PHP 8.2, 8.3, 8.4 and 8.5 (Laravel 13 is excluded on PHP 8.2, which it does not support)
- **New test coverage** for the framework integration surfaces most likely to break on an upgrade
  - `ServiceProviderTest` - container bindings and singleton behaviour, the `calisero` alias,
    config merging, notification channel driver registration, artisan command registration,
    webhook route registration and translation loading
  - `SmsChannelTest` - recipient resolution order (message, `routeNotificationForCalisero()`,
    `$notifiable->phone`), parameter mapping, and the no-op paths when no recipient or message exists
  - `SmsMessageTest` - the fluent `SmsMessage` builder and its constructor arguments
  - `SmsClientTest` - the wrapper's argument mapping, the snake_case/camelCase aliases for every
    optional parameter, missing-parameter rejection, SDK error propagation, balance lookup, and
    delegation of `getMessageStatus()` / `listMessages()` / `deleteMessage()`, none of which had
    any coverage before
  - `ClientFactoryTest` - client construction, and the refusal to build one without an API key
  - Extra cases on existing behaviour: the `callback_url` fallback to `app.url` when the named
    route is absent, token URL-encoding, credit thresholds at the boundary and from numeric
    strings, absent/non-numeric balances, absent and wrong-case webhook statuses, and rejection
    of tokens that are a prefix, extension or case variant of the configured one
- Test suite grew from 28 tests / 94 assertions to 110 tests / 253 assertions

### Changed
- **PHPStan upgraded to 2.x** (`^2.1`, was `^1.11`); the analysis passes at level 6 with no baseline
- **`orchestra/testbench`** constraint is now `^10.0 || ^11.0` (was `^8.0|^9.0|^10.0`);
  Testbench 11 provides the Laravel 13 test harness
- **PHPUnit** constraint widened to `^11.5 || ^12.0 || ^13.0`
- **php-cs-fixer** minimum raised to `^3.75`
- `php` constraint spelled out per supported minor (the previous `^8.2 || ^8.4` was redundant)
- **PHPStan now analyses `tests/` as well as `src/`**, so test code is held to the same level 6
  standard; the `toCalisero()` ignore is scoped to the one file that needs it instead of applying
  repository-wide
- **Validation rule tests converted to data providers.** They previously looped over arrays of
  values inside a single test, so the first failure hid the rest, and they only asserted *that*
  a value failed. They now assert *which* message the rule produced - which immediately exposed
  a mislabelled case: `Test@Company` was listed as an invalid-character case but is 12 characters
  long, so it was really being rejected for its length
- **Shared test doubles extracted** to `tests/Doubles/` (`FakeSdkClient`, `FakeMessageService`,
  `FakeAccountService`, `RecordingSmsClient`) and notifiables to `tests/Fixtures/`, replacing
  per-file classes declared in the same namespace at the bottom of test files. `FakeSdkClient`
  deliberately exposes only the accessors the real SDK has, so a wrapper method reaching for a
  method the SDK lacks now fails in tests
- **Duplicated webhook payload building** extracted into the `BuildsWebhookPayloads` trait; the
  webhook tests no longer repeat a ten-key payload array per test case
- `tests/Fixtures/TestSmsNotification` is now actually used by the channel tests (it was dead code)
- The `Calisero` facade docblock now documents real return types and typed `array<string, mixed>`
  parameters instead of `mixed`, and covers `listMessages()` and `deleteMessage()`
- `composer cs:check` / `cs:fix` no longer set the deprecated `PHP_CS_FIXER_IGNORE_ENV` variable;
  `.php-cs-fixer.php` already allows newer PHP versions via `setUnsupportedPhpVersionAllowed()`

### Security
- **Test phone numbers moved to the ITU-reserved +999 range.** Tests previously used numbers in
  live, allocated ranges (`+40742…`, `+40712…`, `+4012…`, `+1234…`) which may belong to real
  subscribers. Every number now comes from `Tests\Support\TestPhones`, whose values all use
  country code +999 - reserved by ITU-T E.164 and never assigned to any country, so no test value
  can route to a real person.

### Fixed
- **`deleteMessage()` no longer raises a fatal error.** It called a `deleteMessage()` method on the
  upstream SDK client, which does not exist there, so every call ended in
  `Error: Call to undefined method Calisero\Sms\SmsClient::deleteMessage()`. Deletion now goes
  through `messages()->delete()`, like every other message operation. The method had no test
  coverage, which is why the breakage went unnoticed; a regression test now covers it.
- README PHPStan badge now reflects the configured level (6) instead of claiming level 9
- README documents the supported Laravel and PHP combinations, and how to test against a
  specific Laravel version locally

## [1.1.1] - 2025-11-09

### Fixed
- **Verification methods** now correctly use SDK's `verifications()->create()` and `verifications()->validate()` methods
- Fixed `sendVerification()` to properly use `CreateVerificationRequest` DTO
- Fixed `checkVerification()` to properly use `VerificationCheckRequest` DTO and return `GetVerificationResponse`
- Updated test mocks to properly test verification functionality with correct SDK method calls
- Removed unused import in `ServiceProvider` to fix PHP CS Fixer violations

### Changed
- Improved verification method implementations to align with Calisero PHP SDK v2.x architecture
- Enhanced test coverage for verification methods with proper mocking of SDK services
- Better error handling in verification methods with consistent logging

### Technical
- Tests now properly mock `\Calisero\Sms\Services\VerificationService` for verification operations
- Verification methods now return proper DTO responses instead of raw arrays
- All tests passing (28 tests, 94 assertions)
- Code style compliance verified with PHP CS Fixer

## [1.1.0] - 2025-10-09

### Added
- **Verification API support** - Send and check verification codes for 2FA
  - `sendVerification()` method to send auto-generated codes via SMS
  - `checkVerification()` method to verify codes
  - Support for `brand` (simple name, max 120 chars) or `template` (custom message with {code}, max 600 chars)
  - Support for `expires_in` parameter (1-10 minutes, default 5)
  - Codes are automatically generated by Calisero (6 characters, case-insensitive, time-limited)
- **New Artisan commands**
  - `calisero:verification:send` - Send verification codes with brand or template
  - `calisero:verification:check` - Check verification codes from CLI
- **Comprehensive examples** in `examples/` directory
  - `verification_with_brand.php` - Using brand name
  - `verification_with_template.php` - Using custom template
  - `verification_check_with_errors.php` - Complete error handling
  - `complete_2fa_flow.php` - Full 2FA implementation
  - `examples/README.md` - Detailed documentation for all examples
- **Complete 2FA implementation example** in README with all exception types
- **Enhanced documentation** with verification use cases and examples

### Changed
- Updated `SmsClient` interface to include verification methods with brand/template support
- Enhanced README with comprehensive 2FA flow examples and error handling
- Improved logging for all operations using configured log channel consistently
- Better exception handling with Calisero-specific exceptions (ValidationException, NotFoundException, RateLimitedException)
- Fixed `deleteMessage()` to use configured log channel

### Technical
- Compatible with latest `calisero/calisero-php` v2.x with verification support
- Maintains backward compatibility with existing SMS functionality
- Verification codes are SMS-only
- Code generation and expiration handled by Calisero API
- Either `brand` OR `template` (with {code} placeholder) is required
- Template must contain `{code}` placeholder for code insertion
- Template max length: 600 characters
- Brand max length: 120 characters
- `expires_in` range: 1-10 minutes (default: 5)
- Verification codes: 6 characters, alphanumeric, **case-insensitive** (e.g., SBMH0f, sbmh0f, SbMh0F are all valid)

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
