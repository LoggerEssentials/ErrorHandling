<?php

namespace Logger;

use Logger\Loggers\ArrayLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use RuntimeException;

class CoreErrorHandlersExtendedTest extends TestCase {
	public function testEnableExceptionsForErrors_IncludedLevelThrows(): void {
		CoreErrorHandlers::enableExceptionsForErrors(E_USER_WARNING);
		try {
			$this->expectException(\ErrorException::class);
			trigger_error('user warning', E_USER_WARNING);
		} finally {
			@restore_error_handler();
		}
	}

	public function testEnableExceptionsForErrors_ExcludedLevelNoThrow(): void {
		CoreErrorHandlers::enableExceptionsForErrors(E_ALL & ~E_USER_NOTICE);
		try {
			// Should not throw because E_USER_NOTICE is masked out
			trigger_error('user notice', E_USER_NOTICE);
			$this->expectNotToPerformAssertions();
		} finally {
			@restore_error_handler();
		}
	}

	public function testRegisterAssertionHandler_LogsWithContext(): void {
		$tmpDir = sys_get_temp_dir();
		$logFile = tempnam($tmpDir, 'exh-log-');
		if($logFile === false) {
			throw new RuntimeException('Could not create temp file');
		}

		$root = dirname(__DIR__);
		$script = <<<PHP
        <?php
        require {$this->exportPath("$root/vendor/autoload.php")};
        use Logger\\CoreErrorHandlers;
        use Logger\\Loggers\\ArrayLogger;
        use Psr\\Log\\LogLevel;

        \$arrayLogger = ArrayLogger::wrap();
        CoreErrorHandlers::registerAssertionHandler(\$arrayLogger, LogLevel::ERROR);

        // Register shutdown function to save logs even if script ends early
        register_shutdown_function(function () use (\$arrayLogger) {
            file_put_contents({$this->exportPath($logFile)}, json_encode(\$arrayLogger->getMessages()));
        });

        // Intentionally failing assertion (deterministic false, not statically known)
        \$cond = getenv('__ASSERT_FAIL__') === '1';
        assert(\$cond, 'assertion failed');
        PHP;

		$this->runPhpScript($script);

		$json = file_get_contents($logFile);
		$this->assertNotFalse($json);
		$messages = json_decode($json, true);
		if(!is_array($messages)) {
			$this->fail('Invalid log JSON');
		}

		$this->assertNotEmpty($messages, 'Assertion should have been logged');
		$last = end($messages);
		if($last === false) {
			$this->fail('No log entry found');
		}

		$this->assertArrayHasKey('level', $last);
		$this->assertArrayHasKey('message', $last);
		$this->assertArrayHasKey('file', $last['context']);
		$this->assertArrayHasKey('line', $last['context']);
	}

	public function testRegisterExceptionHandler_LogsCriticalAndExits(): void {
		$tmpDir = sys_get_temp_dir();
		$logFile = tempnam($tmpDir, 'exh-log-');
		if($logFile === false) {
			throw new RuntimeException('Could not create temp file');
		}

		// Build a small script that registers the exception handler and throws
		$root = dirname(__DIR__);
		$script = <<<PHP
        <?php
        require {$this->exportPath("$root/vendor/autoload.php")};
        use Logger\\CoreErrorHandlers;
        use Logger\\Loggers\\ArrayLogger;
        use Psr\\Log\\LogLevel;

        // Capture into ArrayLogger so we can dump it at shutdown
        \$arrayLogger = ArrayLogger::wrap();
        CoreErrorHandlers::registerExceptionHandler(\$arrayLogger);
        register_shutdown_function(function () {
            // no-op, ensure shutdown completes
        });
        register_shutdown_function(function () use (\$arrayLogger) {
            file_put_contents({$this->exportPath($logFile)}, json_encode(\$arrayLogger->getMessages()));
        });
        throw new \RuntimeException('boom');
        PHP;

		$this->runPhpScript($script);

		$json = file_get_contents($logFile);
		$this->assertNotFalse($json);
		$msgs = json_decode($json, true);
		if(!is_array($msgs)) {
			$this->fail('Invalid log JSON');
		}
		/** @var array<int, array{level:mixed, message:string, context: array<string, mixed>}> $msgs */
		$this->assertNotEmpty($msgs);
		$last = end($msgs);
		if($last === false) {
			$this->fail('No log entry found');
		}
		$this->assertArrayHasKey('level', $last);
		$this->assertSame('boom', $last['message']);
		$context = $last['context'];
		$this->assertArrayHasKey('exception', $context);
	}

	public function testRegisterFatalErrorHandler_LogsAlertOnFatal(): void {
		$tmpDir = sys_get_temp_dir();
		$logFile = tempnam($tmpDir, 'exh-log-');
		if($logFile === false) {
			throw new RuntimeException('Could not create temp file');
		}

		$root = dirname(__DIR__);
		$script = <<<PHP
        <?php
        require {$this->exportPath("$root/vendor/autoload.php")};
        use Logger\\CoreErrorHandlers;
        use Logger\\Loggers\\StreamLogger;
        use Psr\\Log\\LogLevel;

        \$logger = StreamLogger::wrap({$this->exportPath($logFile)});
        CoreErrorHandlers::registerFatalErrorHandler(\$logger);
        // Trigger a fatal error: call to undefined function
        nonExistingFunctionForFatalTest();
        PHP;

		$code = $this->runPhpScript($script, $exitCode = true);
		$this->assertNotSame(0, $code, 'Subprocess should exit non-zero due to fatal error');

		$content = file_get_contents($logFile);
		$this->assertIsString($content);
		$this->assertStringContainsString('nonExistingFunctionForFatalTest', $content);
	}

	private function runPhpScript(string $script, bool $exitCodeOnly = false): int {
		$tmpFile = tempnam(sys_get_temp_dir(), 'exh-');
		if($tmpFile === false) {
			throw new RuntimeException('Could not create temp file');
		}
		file_put_contents($tmpFile, $script);
		$cmd = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($tmpFile);
		$output = [];
		$code = 0;
		exec($cmd, $output, $code);
		@unlink($tmpFile);

		return $code;
	}

	private function exportPath(string $path): string {
		return var_export($path, true);
	}
}
