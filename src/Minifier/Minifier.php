<?php

declare(strict_types=1);

namespace Baraja\AssetsLoader\Minifier;


final class Minifier
{
	/** @var AssetMinifier[] (format => service) */
	private array $services = [];


	public function minify(string $haystack, string $format): string
	{
		return $this->getMinifier($format)->minify($haystack);
	}


	public function getMinifier(string $format): AssetMinifier
	{
		if (isset($this->services[$format]) === true) {
			return $this->services[$format];
		}
		if ($format === 'css') {
			return new DefaultCssMinifier;
		}

		return new NullMinifier;
	}


	public function addMinifier(AssetMinifier $minifier, string $format): void
	{
		if (isset($this->services[$format]) === true && !$this->services[$format] instanceof $minifier) {
			throw new \LogicException('Minifier for "' . $format . '" has been defined (' . \get_class($this->services[$format]) . ').');
		}

		$this->services[$format] = $minifier;
	}
}
