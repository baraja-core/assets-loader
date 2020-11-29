<?php

declare(strict_types=1);

namespace Baraja\AssetsLoader\Minifier;


final class NullMinifier implements AssetMinifier
{
	public function minify(string $haystack): string
	{
		return $haystack;
	}
}
