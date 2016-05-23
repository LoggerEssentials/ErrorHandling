<?php
namespace Logger;

use Exception;

class StackTracePrinter {
	/**
	 * @param Exception $e
	 * @param string $messageIntro
	 */
	public static function printException(Exception $e, $messageIntro = '') {
		self::p("%s[%s] %s\n", $messageIntro, get_class($e), $e->getMessage());

		foreach($e->getTrace() as $idx => $station) {
			self::formatStation($idx, $station);
		}

		if($e->getPrevious() instanceof Exception) {
			self::p();
			self::printException($e->getPrevious(), 'Previous: ');
		}
	}

	/**
	 * @param int $idx
	 * @param array $station
	 */
	private static function formatStation($idx, $station) {
		$defaults = array(
			'file' => null,
			'line' => null,
			'class' => null,
			'function' => null,
			'type' => null,
			'args' => array(),
		);
		$station = array_merge($defaults, $station);
		self::p("#%- 3s%s:%d\n", $idx, $station['file'] ?: 'unknown', $station['line']);
		if($station['class'] !== null || $station['function'] !== null) {
			$params = array();
			foreach(is_array($station['args']) ? $station['args'] : array() as $argument) {
				if(is_array($argument)) {
					$params[] = sprintf('array%d', count($argument));
				} elseif(is_object($argument)) {
					$params[] = sprintf('%s', get_class($argument));
				} else {
					$params[] = gettype($argument);
				}
			}
			if(strpos($station['function'], '{closure}') !== false && $station['class'] !== null) {
				$station['function'] = '{closure}';
			}
			self::p("    %s%s%s%s%s%s\n", $station['class'], $station['type'], $station['function'], "(", join(', ', $params), ")");
		}
	}

	/**
	 * @param string $format
	 */
	private static function p($format = "\n") {
		$fp = fopen('php://stderr', 'w');
		fwrite($fp, vsprintf($format, array_slice(func_get_args(), 1)));
		fclose($fp);
	}
}
