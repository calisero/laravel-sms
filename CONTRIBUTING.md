# Contributing to calisero/laravel-sms

Thanks for taking the time to contribute! This document explains how to set up your environment, the standards we follow, and how to submit high‑quality issues and pull requests.

## Table of Contents
1. Philosophy & Scope
2. Getting Started
3. Development Workflow
4. Quality Gates (CS / Static Analysis / Tests)
5. Coding Standards
6. Commit Messages & Branching
7. Adding Features (Guidelines)
8. Tests: Writing & Structure
9. Performance & Reliability Considerations
10. Pull Request Checklist
11. Reporting Issues
12. Security Disclosure
13. Release / Versioning Notes
14. Helpful Commands Summary

---
## 1. Philosophy & Scope
This package provides an idiomatic Laravel integration for the Calisero SMS API. Goals:
- Stay thin: delegate transport/business logic to the underlying Calisero PHP SDK.
- Feel native to Laravel (facade, service provider, notification channel, validation rules, events, webhooks).
- Remain framework‑aligned (Laravel 12.x and 13.x at present) and semantically versioned.
- Prefer clarity over magic.

Non‑Goals:
- Re‑implement the HTTP client — that belongs to the SDK.
- Support legacy Laravel versions (< 12) unless specifically requested & justifiable.

---
## 2. Getting Started

### Prerequisites
- PHP: 8.2 or 8.4 (matrix also runs 8.3)
- Composer v2
- Git

### Clone & Install
```bash
git clone https://github.com/calisero/laravel-sms.git
cd laravel-sms
composer install
```

### Sanity Check
```bash
composer qa
```
You should see all checks pass (or only style diffs if you intentionally changed formatting).

---
## 3. Development Workflow
1. Create a feature branch from `main`:
   ```bash
   git checkout -b feature/<short-description>
   ```
2. Make changes with small, focused commits.
3. Add / adjust tests.
4. Run the full QA suite (`composer qa`).
5. Open a Pull Request (PR) against `main`.
6. Address CI feedback (GitHub Actions runs: CS, PHPStan, Test matrix).

---
## 4. Quality Gates (CS / Static Analysis / Tests)
| Gate | Command | Notes |
|------|---------|-------|
| Code Style | `composer cs:check` | Dry run (no changes). |
| Auto‑Fix Style | `composer cs:fix` | Applies fixes (review diff!). |
| Static Analysis | `composer stan` | Uses PHPStan (level inherited from config). |
| Tests | `composer test` | PHPUnit modern config. |
| Full Pipeline | `composer qa` | validate + cs:check + stan + test. |

> php-cs-fixer is allowed to run on PHP versions newer than the project minimum through
> `setUnsupportedPhpVersionAllowed()` in `.php-cs-fixer.php`. The old `PHP_CS_FIXER_IGNORE_ENV`
> environment variable is deprecated upstream and is no longer set by the composer scripts.

When changing dependency constraints, verify both supported Laravel majors locally:

```bash
composer update --with="illuminate/support:^13.0" --with="illuminate/notifications:^13.0" \
  --with="illuminate/validation:^13.0" --with="illuminate/routing:^13.0" --with="illuminate/console:^13.0"
composer qa

composer update --with="illuminate/support:^12.0" --with="illuminate/notifications:^12.0" \
  --with="illuminate/validation:^12.0" --with="illuminate/routing:^12.0" --with="illuminate/console:^12.0"
composer qa
```

---
## 5. Coding Standards
- Base rules: PSR-12 + project customizations (`.php-cs-fixer.php`).
- Short array syntax, ordered imports, no unused imports, trailing commas in multiline.
- Keep methods cohesive; avoid giant helpers.
- No debug `var_dump()/dd()` in committed code.
- Use strict types where practical (consider adding `declare(strict_types=1);` in new files—stay consistent with existing style; if you add it, apply broadly in a follow‑up PR, not piecemeal).

### PHPStan
- Level 6 over both `src/` and `tests/`.
- If you need to suppress a false positive, prefer a narrow `@phpstan-ignore-line` *with a reason*.
- Avoid `@phpstan-ignore-next-line` in multiple adjacent lines—refactor instead.
- Scope any `ignoreErrors` entry in `phpstan.neon` to a `path`, so it cannot mask the same
  problem elsewhere in the repository.

### Tests
- Follow `test_<intent>` naming, no `@test` annotations.
- One logical expectation per concept; use data providers when variation count grows.
- **Never hard-code a phone number with a live country code**, not even an invented one: `+40`,
  `+1`, `+44` and friends are allocated ranges, and a plausible-looking number may belong to a
  real person. Take every number from `Calisero\LaravelSms\Tests\Support\TestPhones`, whose
  values all use country code `+999` - reserved by ITU-T E.164 and never assigned to any country.
  Add a constant there if you need another one.
- Shared test doubles live in `tests/Doubles/`, notifiables and notifications in `tests/Fixtures/`,
  reusable helper traits in `tests/Concerns/`. Do not declare helper classes at the bottom of a
  test file: they share the `Tests\Unit` namespace and collide across files.
- `composer stan` covers `tests/` as well as `src/`, so test code must be type-clean too.

---
## 6. Commit Messages & Branching

### Branch Naming
```
feature/<concise-topic>
fix/<issue-or-bug>
chore/<maintenance-task>
refactor/<internal-change>
```

### Conventional Commits (Preferred)
Format: `type(scope): summary`
Examples:
```
feat(notification): add delivery report parsing
fix(validation): correct E.164 edge case for 3 digit country code
chore(ci): add php 8.4 to test matrix
```
Allowed types: `feat`, `fix`, `chore`, `docs`, `refactor`, `test`, `ci`, `perf`, `build`.

### Commit Guidelines
- Keep summary ≤ 72 chars.
- Body (optional): explain rationale, not just *what* you did.
- Reference issues: `Closes #42` where appropriate.

---
## 7. Adding Features (Guidelines)
| Area | Guidance |
|------|----------|
| New Validation Rule | Place in `src/Validation/Rules/`, update aggregator `Rule` helper, add unit tests. |
| New Event | Add under `src/Events/`, document payload shape in PHPDoc, update README if public. |
| Notification Channel | Extend existing pattern; ensure integration test with a fake notifiable. |
| Config Option | Add to `config/calisero.php` + doc in README + default sane value. |
| Webhook Handling | Validate signatures early; throw domain‑specific exceptions when invalid. |
| Breaking Changes | Open an issue first; discuss semantic version impact. |

### Do Not
- Add framework‑specific helpers that duplicate native Laravel features.
- Introduce heavy dependencies without prior discussion.

---
## 8. Tests: Writing & Structure
- Base class: `tests/TestCase.php` (extends Orchestra Testbench).
- Unit tests live under `tests/Unit/`.
- Prefer fast, deterministic tests—no external network calls.
- For new logic, add both positive and negative cases.
- Keep fixtures minimal; inline arrays preferable over large fixture files unless reused widely.

### Running Specific Tests
```bash
# Filter by class
vendor/bin/phpunit --filter PhoneE164Test
# Filter by method
vendor/bin/phpunit --filter test_it_fails_for_invalid_numbers
```

---
## 9. Performance & Reliability Considerations
- Avoid unnecessary API calls inside loops—batch or move outside when feasible.
- Validate inputs early; fail fast with clear exceptions.
- When adding retry logic, ensure idempotency keys are enforced.

---
## 10. Pull Request Checklist
Before marking “Ready for Review”:
- [ ] Branch is up to date with `main` (rebase preferred over merge commits).
- [ ] `composer qa` passes locally.
- [ ] Added / updated tests for all new behavior.
- [ ] README / docs updated (if user-facing change).
- [ ] No leftover debug statements or commented code.
- [ ] No broad ignore annotations (`@phpstan-ignore-line`) without justification.

---
## 11. Reporting Issues
When opening an issue, include:
- Environment: PHP version, Laravel version.
- Steps to reproduce (minimal reproducible example > descriptive prose).
- Expected vs actual behavior.
- Error logs / stack traces (trim to relevant frames).
- Version of this package (and if possible, commit hash).

Feature Requests:
- State the problem first; describe the use case.
- Suggest API shape (optional) — examples help.

---
## 12. Security Disclosure
Please **do not** open public issues for security vulnerabilities. Follow the Security Policy:
- See: `SECURITY.md` (or repository security policy page)
- Provide clear reproduction details privately.

---
## 13. Release / Versioning Notes
- Semantic Versioning: MAJOR.MINOR.PATCH
- Changelog maintained in `CHANGELOG.md`.
- Keep unreleased changes grouped under a "[Unreleased]" heading until tagged.
- Git tags should match Packagist releases (e.g., `v1.2.0`).
- Avoid bundling unrelated features in a single release PR.

---
## 14. Helpful Commands Summary
```bash
# Code style (check / fix)
composer cs:check
composer cs:fix

# Static analysis
composer stan

# Tests
composer test

# Full pipeline
composer qa

# Update dependencies (no dev interaction)
composer update --no-interaction

# Generate a classmap autoload refresh (rarely needed manually)
composer dump-autoload -o
```

---
## Questions?
Open a discussion or issue if something here is unclear or you want to propose workflow improvements.

Thanks again for helping improve `calisero/laravel-sms`! 🙌

