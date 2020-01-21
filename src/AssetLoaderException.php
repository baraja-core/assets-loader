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

}