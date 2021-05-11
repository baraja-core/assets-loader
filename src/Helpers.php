<?php

declare(strict_types=1);

namespace Baraja\AssetsLoader;


use Baraja\Url\Url;
use Nette\Http\Request;

final class Helpers
{
	/** @throws \Error */
	public function __construct()
	{
		throw new \Error('Class ' . static::class . ' is static and cannot be instantiated.');
	}


	/**
	 * Return current API path by current HTTP URL.
	 * In case of CLI return empty string.
	 */
	public static function processPath(Request $httpRequest): string
	{
		return trim(
			str_replace(
				rtrim($httpRequest->getUrl()->withoutUserInfo()->getBaseUrl(), '/'),
				'',
				Url::get()->getCurrentUrl()
			),
			'/'
		);
	}


	public static function formatRoute(
		string $module,
		string $presenter = 'Homepage',
		string $action = 'default'
	): string {
		return self::firstUpper($module) . ':' . self::firstUpper($presenter) . ':' . $action;
	}


	public static function formatRouteToPath(string $route): string
	{
		if ($route === 'Error4xx:default') {
			return 'error4xx-default';
		}
		if (preg_match('/^(?<module>[^:]+):(?<presenter>[^:]+):(?<action>[^:]+)$/', $route, $parser)) {
			return self::firstLower($parser['module'])
				. '-' . self::firstLower($parser['presenter'])
				. '-' . self::firstLower($parser['action']);
		}

		throw new \InvalidArgumentException('Can not parse route format, because haystack "' . $route . '" given.');
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
