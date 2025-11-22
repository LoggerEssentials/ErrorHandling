<?php

namespace Logger;

use ErrorException;
use PHPUnit\Framework\TestCase;


class CoreErrorHandlersTest extends TestCase {
	protected function tearDown(): void {
		// Restore handlers changed by the code under test
		@restore_error_handler();
		@restore_exception_handler();
	}

	public function testEnableExceptionsForErrors_Bitmask1(): void {
		CoreErrorHandlers::enableExceptionsForErrors(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED & ~E_USER_WARNING);
		// Trigger a masked-out warning (not a deprecation) to avoid PHPUnit deprecation reporting
		trigger_error('Some user warning', E_USER_WARNING);
		$this->expectNotToPerformAssertions();
	}

	public function testEnableExceptionsForErrors_Bitmask2(): void {
		CoreErrorHandlers::enableExceptionsForErrors(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
		$this->expectException(ErrorException::class);
		trigger_error('Irgend ein strictly-error', E_USER_ERROR);
	}
}
