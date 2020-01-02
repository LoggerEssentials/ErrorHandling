<?php
namespace Logger;

use ErrorException;
use Exception;
use Logger\Filters\LogLevelRangeFilter;
use Logger\Loggers\LoggerCollection;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class CoreErrorHandlers {
	/**
	 * @var array
	 */
	private static $phpErrorLevels = array(
		E_NOTICE => LogLevel::NOTICE,
		E_DEPRECATED => LogLevel::NOTICE,
		E_USER_DEPRECATED => LogLevel::NOTICE,
		E_WARNING => LogLevel::WARNING,
		E_STRICT => LogLevel::WARNING,
		E_USER_WARNING => LogLevel::WARNING,
		E_CORE_WARNING => LogLevel::WARNING,
		E_ERROR => LogLevel::ERROR,
		E_USER_ERROR => LogLevel::ERROR,
	);

	/**
	 * @param int|null $bitmask
	 */
	public static function enableExceptionsForErrors($bitmask = null) {
		set_error_handler(function ($level, $message, $file, $line) use ($bitmask) {
			// PHP-7 fix: What once was an E_STRICT is now an E_WARNING:
			if(preg_match('/^Declaration of .*? should be compatible with/', $message)) {
				$level = E_STRICT;
			}
			if (0 === error_reporting()) {
				return false;
			}
			if($bitmask & $level) {
				throw new ErrorException($message, 0, $level, $file, $line);
			}
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
			assert_options(ASSERT_CALLBACK, function ($file, $line, $message) use ($errorLogger, $logLevel) {
				$errorLogger->log($logLevel, $message, array(
					'file' => $file,
					'line' => $line
				));
			});
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
			$errorLevels = self::$phpErrorLevels;
			register_shutdown_function(function () use ($errorLogger, $errorLevels) {
				$error = error_get_last();
				if($error !== null && $error['type'] === E_ERROR) {
					$errorLogger = new LogLevelRangeFilter($errorLogger, LogLevel::ERROR);
					$errorLogger->log(LogLevel::ALERT, $error['message'], $error);
				}
			});
		}
		$errorLogger->add($logger);
	}

	/**
	 * @param LoggerInterface $logger
	 */
	public static function registerExceptionHandler(LoggerInterface $logger) {
		static $errorLogger = null;
		if($errorLogger === null) {
			$errorLogger = new LoggerCollection();
			set_exception_handler(function ($exception) use ($errorLogger) {
				/** @var \Exception|\Throwable $exception */
				$errorLogger = new LogLevelRangeFilter($errorLogger, LogLevel::ERROR);
				$errorLogger->log(LogLevel::CRITICAL, $exception->getMessage(), array('exception' => $exception));
				if($exception instanceof Exception) {
					StackTracePrinter::printException($exception, "PHP Fatal Error: Uncaught exception: ");
					die(1);
				}
			});
		}
		$errorLogger->add($logger);
	}
}
