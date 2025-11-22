<?php

namespace Logger;

use ErrorException;
use Logger\Filters\LogLevelRangeFilter;
use Logger\Loggers\LoggerCollection;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Throwable;

class CoreErrorHandlers {
	/**
	 * @param int $bitmask
	 */
	public static function enableExceptionsForErrors(int $bitmask = E_ALL) {
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
	public static function registerAssertionHandler(LoggerInterface $logger, $logLevel) {
		static $errorLogger = null;
		if($errorLogger === null) {
			$errorLogger = new LoggerCollection();
			assert_options(ASSERT_ACTIVE, true);
			assert_options(ASSERT_WARNING, false);
			assert_options(ASSERT_CALLBACK, static fn(string $file, int $line, ?string $message) =>
				$errorLogger->log($logLevel, $message, [
					'file' => $file,
					'line' => $line,
				])
			);
		}
		$errorLogger->add($logger);
	}

	/**
	 * @param LoggerInterface $logger
	 */
	public static function registerFatalErrorHandler(LoggerInterface $logger) {
		static $errorLogger = null;
		if($errorLogger === null) {
			$errorLogger = new LoggerCollection();
			$fatalLevels = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
			register_shutdown_function(function () use ($errorLogger, $fatalLevels) {
				$error = error_get_last();
				if($error !== null && in_array($error['type'], $fatalLevels, true)) {
					$errorLogger = new LogLevelRangeFilter($errorLogger, LogLevel::ERROR);
					$errorLogger->log(LogLevel::ALERT, $error['message'], $error);
				}
			});
		}
		$errorLogger->add($logger);
	}

	public static function registerExceptionHandler(LoggerInterface $logger) {
		static $errorLogger = null;
		if($errorLogger === null) {
			$errorLogger = new LoggerCollection();
			set_exception_handler(static function ($exception) use ($errorLogger) {
				/** @var Throwable $exception */
				$errorLogger = new LogLevelRangeFilter($errorLogger, LogLevel::ERROR);
				try {
					$exceptionAsArray = self::getExceptionAsArray($exception, true, true);
					$errorLogger->log(LogLevel::CRITICAL, $exception->getMessage(), ['exception' => $exceptionAsArray]);
				} catch(Throwable) {
					$exceptionAsArray1 = self::getExceptionAsArray($exception, false, false);
					$errorLogger->log(LogLevel::CRITICAL, $exception->getMessage(), ['exception' => $exceptionAsArray1]);
				}
				StackTracePrinter::printException($exception, "PHP Fatal Error: Uncaught: ");
				die(1);
			});
		}
		$errorLogger->add($logger);
	}

	/**
	 * @param Throwable $exception
	 * @param bool $previous
	 * @param bool $withTrace
	 * @return array|null
	 */
	private static function getExceptionAsArray($exception, bool $previous, bool $withTrace) {
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
