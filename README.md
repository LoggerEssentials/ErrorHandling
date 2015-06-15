ErrorHandling
=============
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/LoggerEssentials/ErrorHandling/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/LoggerEssentials/ErrorHandling/?branch=master)
[![Build Status](https://travis-ci.org/LoggerEssentials/ErrorHandling.svg?branch=master)](https://travis-ci.org/LoggerEssentials/ErrorHandling)
[![Latest Stable Version](https://poser.pugx.org/logger/errorhandling/version.svg)](https://packagist.org/packages/logger/errorhandling)
[![License](https://poser.pugx.org/logger/errorhandling/license.svg)](https://packagist.org/packages/logger/errorhandling)

Errorhandling extension for PSR-3 compatible loggers

## Why should I care?

`ErrorHandling` helps you getting every type of problem in your logging-channels.

## How does it work?

`ErrorHandling` uses most of PHP's capabilities to capture _notices_, _warnings_, _errors_, _exceptions_ and even _fatal errors_ in most situations and redirect them to user-defined PSR-3 compatible logging channels. 

Typically you would end up having something like this:

```PHP
$coreHandlers = new CoreErrorHandlers();
$coreHandlers->enableExceptionsForErrors();
$coreHandlers->registerAssertionHandler($logger, LogLevel::DEBUG);
$coreHandlers->registerExceptionHandler($logger);
$coreHandlers->registerFatalErrorHandler($logger);
```

This will register trigger-callback-functions for asserts, exceptions and fatal errors. It also registers exception-handlers for every notice, warning and error.

Note: You need to run the registration-process on every run. Yes, I think you already got it, but just in case: Any problems only get caught if the registration was already made at the time the problem occurs.
