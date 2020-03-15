<?php

declare(strict_types=1);

namespace Baraja\AssetsLoader;


final class AssetLoaderException extends \RuntimeException
{

	/**
	 * @param string $name
	 */
	public static function invalidFileName(string $name): void
	{
		throw new self('Invalid asset filename "' . $name . '". Did you mean "' . $name . '.js"?');
	}

	public static function dataIsEmpty(): void
	{
		throw new self('Data is empty. Did you registered "LoaderExtension" in Neon?');
	}

	/**
	 * @param string $route
	 */
	public static function routeIsInInvalidFormat(string $route): void
	{
		throw new self(
			'Route "' . $route . '" is invalid. '
			. 'Route must be absolute "Module:Presenter:action" or end '
			. 'with dynamic part in format "Module:*" or "Module:Presenter:*".'
		);
	}

}