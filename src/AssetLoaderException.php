<?php

declare(strict_types=1);

namespace Baraja\AssetsLoader;


final class AssetLoaderException extends \RuntimeException
{
	public static function routeIsInInvalidFormat(string $route): void
	{
		throw new self(
			'Route "' . $route . '" is invalid. '
			. 'Route must be absolute "Module:Presenter:action" or end '
			. 'with dynamic part in format "Module:*" or "Module:Presenter:*".',
		);
	}
}
