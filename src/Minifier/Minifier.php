<?php

declare(strict_types=1);

namespace Baraja\AssetsLoader\Minifier;


use Nette\Caching\Cache;
use Nette\Caching\Storage;

final class Minifier
{
	/** @var array<string, AssetMinifier> (format => service) */
	private array $services = [];

	private ?Cache $cache = null;

	private string $cacheExpiration = '1 hour';


	public function __construct(?Storage $storage = null)
	{
		if ($storage !== null) {
			$this->cache = new Cache($storage, 'baraja-assets-loader-minifier');
		}
	}


	public function minify(string $haystack, string $format): string
	{
		$key = sprintf('%s-%s', $format, md5($haystack));
		if ($this->cache !== null) {
			$cache = $this->cache->load($key);
			if (is_string($cache)) {
				return $cache;
			}
		}
		$return = $this->getMinifier($format)->minify($haystack);
		if ($this->cache !== null) {
			$this->cache->save($key, $return, [
				Cache::EXPIRE => $this->cacheExpiration,
				Cache::TAGS => [$format, 'minifier'],
			]);
		}

		return $return;
	}


	public function getMinifier(string $format): AssetMinifier
	{
		if (isset($this->services[$format]) === true) {
			return $this->services[$format];
		}
		if ($format === 'css') {
			return new DefaultCssMinifier;
		}
		if ($format === 'js') {
			return new DefaultJsMinifier;
		}

		return new NullMinifier;
	}


	public function addMinifier(AssetMinifier $minifier, string $format): void
	{
		if (isset($this->services[$format]) === true && !$this->services[$format] instanceof $minifier) {
			throw new \LogicException(sprintf('Minifier for "%s" has been defined (%s).', $format, $this->services[$format]::class));
		}

		$this->services[$format] = $minifier;
	}


	public function setCacheExpiration(string $cacheExpiration): void
	{
		$this->cacheExpiration = $cacheExpiration;
	}
}
