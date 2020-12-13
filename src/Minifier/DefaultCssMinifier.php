<?php

declare(strict_types=1);

namespace Baraja\AssetsLoader\Minifier;


final class DefaultCssMinifier implements AssetMinifier
{
	public function minify(string $haystack): string
	{
		return (string) preg_replace_callback(
			'#[ \t\r\n]+|<(/)?(textarea|pre|script)(?=\W)#si',
			static function ($m): string {
				if (empty($m[2])) {
					return ' ';
				}

				return $m[0];
			},
			$haystack
		);
	}
}
