<?php
namespace Logger;

use ErrorException;
use PHPUnit\Framework\TestCase;

class CoreErrorHandlersTest extends TestCase {
	public function testEnableExceptionsForErrors_Bitmask1() {
		CoreErrorHandlers::enableExceptionsForErrors(E_ALL ^ E_STRICT ^ E_DEPRECATED ^ E_USER_DEPRECATED);
		trigger_error('Irgend ein strictly-error', E_USER_DEPRECATED);
		$this->assertTrue(true);
	}

	public function testEnableExceptionsForErrors_Bitmask2() {
		CoreErrorHandlers::enableExceptionsForErrors(E_ALL ^ E_STRICT ^ E_DEPRECATED ^ E_USER_DEPRECATED);
		$this->expectException(ErrorException::class);
		trigger_error('Irgend ein strictly-error', E_USER_ERROR);
	}
}
