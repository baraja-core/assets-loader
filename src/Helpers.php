<?php

declare(strict_types=1);

namespace Baraja\AssetsLoader;


final class Helpers
{
	private function __construct()
	{
	}


	public static function formatRoute(
		string $module,
		string $presenter = 'Homepage',
		string $action = 'default',
	): string {
		return self::firstUpper($module) . ':' . self::firstUpper($presenter) . ':' . $action;
	}


	public static function formatRouteToPath(string $route): string
	{
		if ($route === 'Error4xx:default') {
			return 'error4xx-default';
		}
		if (preg_match('/^(?<module>[^:]+):(?<presenter>[^:]+):(?<action>[^:]+)$/', $route, $parser) === 1) {
			return self::firstLower($parser['module'])
				. '-' . self::firstLower($parser['presenter'])
				. '-' . self::firstLower($parser['action']);
		}

		throw new \InvalidArgumentException(sprintf('Can not parse route format, because haystack "%s" given.', $route));
	}


	public static function firstUpper(string $s): string
	{
		return strtoupper($s[0] ?? '') . mb_substr($s, 1, null, 'UTF-8');
	}


	public static function firstLower(string $s): string
	{
		return strtolower($s[0] ?? '') . mb_substr($s, 1, null, 'UTF-8');
	}
}
