<?php

declare(strict_types=1);

namespace Baraja\AssetsLoader\Minifier;


use JShrink\JShrinkMinifier;

final class DefaultJsMinifier implements AssetMinifier
{
	public function minify(string $haystack): string
	{
		return JShrinkMinifier::minify($haystack);
	}
}
