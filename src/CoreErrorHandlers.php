<?php

namespace Logger;

use ErrorException;
use Logger\Filters\LogLevelRangeFilter;
use Logger\Loggers\LoggerCollection;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Throwable;

/**
 * @phpstan-type TExceptionArray array{
 *     message: string,
 *     code: int|string,
 *     file: string,
 *     line: int,
 *     trace: mixed[]|string,
 *     previous: array<string, mixed>|null
 * }
 */
class CoreErrorHandlers {
	private const FATAL_LEVELS = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

	private static ?LoggerCollection $assertionLogger = null;
	private static ?LoggerCollection $fatalLogger = null;
	private static ?LoggerCollection $exceptionLogger = null;

	/**
	 * @param int $bitmask
	 */
	public static function enableExceptionsForErrors(int $bitmask = E_ALL): void {
		set_error_handler(function ($level, $message, $file, $line) use ($bitmask) {
			if(0 === error_reporting()) {
				return false;
			}
			if(($bitmask & $level) !== 0) {
				throw new ErrorException($message, 0, $level, $file, $line);
			}

			return false;
		});
	}

	/**
	 * @param LoggerInterface $logger
	 * @param string $logLevel PSR-3 Log-Level
	 */
	public static function registerAssertionHandler(LoggerInterface $logger, string $logLevel): void {
		if(self::$assertionLogger === null) {
			self::$assertionLogger = new LoggerCollection();

			// PHP 8.0+ throws AssertionError by default (assert.exception=1)
			// We register an exception handler specifically for assertions
			$previousHandler = set_exception_handler(null);
			restore_exception_handler();

			set_exception_handler(static function (Throwable $exception) use ($previousHandler, $logLevel): void {
				if($exception instanceof \AssertionError) {
					// Log the assertion failure
					self::$assertionLogger?->log($logLevel, $exception->getMessage(), [
						'file' => $exception->getFile(),
						'line' => $exception->getLine(),
					]);
					// Don't re-throw - assertion has been handled
					return;
				}

				// Pass other exceptions to previous handler or re-throw
				if($previousHandler !== null) {
					$previousHandler($exception);
				} else {
					throw $exception;
				}
			});
		}
		self::$assertionLogger->add($logger);
	}

	/**
	 * @param LoggerInterface $logger
	 */
	public static function registerFatalErrorHandler(LoggerInterface $logger): void {
		if(self::$fatalLogger === null) {
			self::$fatalLogger = new LoggerCollection();
			register_shutdown_function(function (): void {
				$error = error_get_last();
				if($error !== null && in_array($error['type'], self::FATAL_LEVELS, true)) {
					$fl = new LogLevelRangeFilter(self::$fatalLogger ?? new LoggerCollection(), LogLevel::ERROR);
					$fl->log(LogLevel::ALERT, $error['message'], $error);
				}
			});
		}
		self::$fatalLogger->add($logger);
	}

	public static function registerExceptionHandler(LoggerInterface $logger): void {
		if(self::$exceptionLogger === null) {
			self::$exceptionLogger = new LoggerCollection();
			set_exception_handler(static function (Throwable $exception): void {
				$log = new LogLevelRangeFilter(self::$exceptionLogger ?? new LoggerCollection(), LogLevel::ERROR);
				try {
					$exceptionAsArray = self::getExceptionAsArray($exception, true, true);
					$log->log(LogLevel::CRITICAL, $exception->getMessage(), ['exception' => $exceptionAsArray]);
				} catch(Throwable) {
					$exceptionAsArray1 = self::getExceptionAsArray($exception, false, false);
					$log->log(LogLevel::CRITICAL, $exception->getMessage(), ['exception' => $exceptionAsArray1]);
				}
				StackTracePrinter::printException($exception, "PHP Fatal Error: Uncaught: ");
				die(1);
			});
		}
		self::$exceptionLogger->add($logger);
	}

	/**
	 * @param Throwable|null $exception
	 * @param bool $previous
	 * @param bool $withTrace
	 * @return array<string, mixed>|null
	 * @phpstan-return TExceptionArray|null
	 */
	private static function getExceptionAsArray(?Throwable $exception, bool $previous, bool $withTrace): ?array {
		if($exception === null) {
			return null;
		}

		return [
			'message' => $exception->getMessage(),
			'code' => $exception->getCode(),
			'file' => $exception->getFile(),
			'line' => $exception->getLine(),
			'trace' => $withTrace ? $exception->getTrace() : $exception->getTraceAsString(),
			'previous' => $previous ? self::getExceptionAsArray($exception->getPrevious(), $previous, $withTrace) : null,
		];
	}
}
