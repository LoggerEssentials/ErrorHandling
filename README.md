ErrorHandling
=============
[![Latest Stable Version](https://poser.pugx.org/logger/errorhandling/version.svg)](https://packagist.org/packages/logger/errorhandling)
[![License](https://poser.pugx.org/logger/errorhandling/license.svg)](https://packagist.org/packages/logger/errorhandling)

Overview
- ErrorHandling provides drop‑in handlers that route all PHP problems to your PSR‑3 logger: notices, warnings, deprecations, assertions, exceptions, and many fatals.
- Designed for modern PHP (8.x, incl. 8.5) and integrates with logger/essentials out of the box, but works with any PSR‑3 `LoggerInterface`.

Requirements
- PHP >= 8.0
- A PSR‑3 compatible logger (e.g., `logger/essentials`)

Install
- Composer: `composer require logger/errorhandling`

Quick Start
```php
use Logger\CoreErrorHandlers;
use Psr\Log\LogLevel;

// Turn PHP errors into exceptions (configurable mask, defaults to E_ALL)
CoreErrorHandlers::enableExceptionsForErrors();

// Log assertions through your PSR-3 logger at a chosen level
CoreErrorHandlers::registerAssertionHandler($logger, LogLevel::WARNING);

// Log uncaught exceptions (and print a compact stack trace to STDERR)
CoreErrorHandlers::registerExceptionHandler($logger);

// Log fatal errors such as E_ERROR/E_PARSE/E_CORE_ERROR/E_COMPILE_ERROR
CoreErrorHandlers::registerFatalErrorHandler($logger);
```

What It Does
- Exceptions for errors: Converts PHP error levels (by bitmask) into `ErrorException` you can catch, or let bubble into the exception handler.
- Assertion logging: Captures `assert()` callbacks and logs message + file/line without emitting warnings.
- Exception logging: Catches uncaught `Throwable`, logs at `critical`, and prints a readable stack trace to STDERR before exiting with status 1.
- Fatal error logging: On shutdown, inspects the last error and logs `alert` for typical fatal types.

API
- `CoreErrorHandlers::enableExceptionsForErrors(int $bitmask = E_ALL): void`
  - Throw `ErrorException` for any error whose level matches `$bitmask`.
- `CoreErrorHandlers::registerAssertionHandler(LoggerInterface $logger, string $logLevel): void`
  - Route failed assertions to `$logger` at the given PSR‑3 level, with `file` and `line` context.
- `CoreErrorHandlers::registerExceptionHandler(LoggerInterface $logger): void`
  - Log uncaught exceptions as `critical` with rich context; prints stack trace and exits with code 1.
- `CoreErrorHandlers::registerFatalErrorHandler(LoggerInterface $logger): void`
  - On shutdown, log last fatal error (`E_ERROR`, `E_PARSE`, `E_CORE_ERROR`, `E_COMPILE_ERROR`) as `alert`.

Configuration Tips
- Call the registration methods early in your bootstrap so problems are captured.
- You can combine handlers (typical usage registers all of them).
- To exclude specific severities from becoming exceptions, pass a masked bitmask, e.g. `E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED`.

Development
- Run tests: `composer run tests`
- Static analysis: `composer run phpstan`

Compatibility Notes
- `E_STRICT` is not handled specially (folded into other levels on modern PHP).
- Handlers use `Throwable`, not just `Exception`, to cover engine errors in PHP 7/8.

License
- MIT — see LICENSE for details.
