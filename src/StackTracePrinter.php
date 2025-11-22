<?php

namespace Logger;

use Throwable;

class StackTracePrinter {
	/**
	 * @param Throwable $e
	 * @param string $messageIntro
	 */
    public static function printException(Throwable $e, $messageIntro = ''): void {
        self::p("%s[%s] %s\n", $messageIntro, get_class($e), $e->getMessage());

		foreach($e->getTrace() as $idx => $station) {
			self::formatStation($idx, $station);
		}

		if($e->getPrevious() instanceof Throwable) {
			self::p();
			self::printException($e->getPrevious(), 'Previous: ');
		}
	}

	/**
	 * @param int $idx
	 * @param array $station
	 */
    /**
     * @param int $idx
     * @param array<string, mixed> $station
     */
    private static function formatStation($idx, $station): void {
        $defaults = [
            'file' => null,
            'line' => null,
            'class' => null,
            'function' => null,
            'type' => null,
            'args' => [],
        ];
        $station = array_merge($defaults, $station);
        self::p("#%- 3s%s:%d\n", $idx, $station['file'] ?: 'unknown', $station['line']);
        if($station['class'] !== null || $station['function'] !== null) {
            $params = [];
            $args = $station['args'];
            if (!is_array($args)) {
                $args = [];
            }
            foreach($args as $argument) {
                if(is_array($argument)) {
                    $params[] = sprintf('array%d', count($argument));
                } elseif(is_object($argument)) {
                    $params[] = sprintf('%s', get_class($argument));
                } else {
                    $params[] = gettype($argument);
                }
            }
            $func = $station['function'];
            if(is_string($func) && strpos($func, '{closure}') !== false && $station['class'] !== null) {
                $station['function'] = '{closure}';
            }
            self::p("    %s%s%s%s%s%s\n", $station['class'], $station['type'], $station['function'], "(", join(', ', $params), ")");
        }
    }

	/**
	 * @param string $format
	 */
    private static function p($format = "\n"): void {
        $fp = fopen('php://stderr', 'w');
        if ($fp !== false) {
            $args = array_slice(func_get_args(), 1);
            /** @var array<bool|float|int|string|null> $args */
            $args = $args;
            fwrite($fp, vsprintf($format, $args));
            fclose($fp);
        }
    }
}
