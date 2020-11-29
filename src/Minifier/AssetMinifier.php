<?php

declare(strict_types=1);

namespace Baraja\AssetsLoader\Minifier;


interface AssetMinifier
{
	public function minify(string $haystack): string;
}
