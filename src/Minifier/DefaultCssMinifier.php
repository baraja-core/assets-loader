<?php

declare(strict_types=1);

namespace Baraja\AssetsLoader\Minifier;


final class DefaultCssMinifier implements AssetMinifier
{
	public function minify(string $haystack): string
	{
		$return = (string) preg_replace_callback(
			'#[ \t\r\n]+|<(/)?(textarea|pre)(?=\W)#i',
			fn (array $match): string => empty($match[2]) ? ' ' : $match[0],
			$haystack,
		);
		$return = (string) preg_replace('/(\w|;)\s+({|})\s+(\w|\.|#)/', '$1$2$3', $return);
		$return = str_replace(';}', '}', $return);
		$return = (string) preg_replace('/(\w)\s*:\s+(\w|#|-|.)/', '$1:$2', $return);
		return (string) preg_replace('/\s*\/\*+[^\*]+\*+\/\s*/', '', $return);
	}
}
