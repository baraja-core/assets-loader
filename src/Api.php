<?php

declare(strict_types=1);

namespace Baraja\AssetsLoader;


use Nette\DI\Container;

final class Api
{

	/**
	 * @var string[]
	 */
	private static $formatHeaders = [
		'js' => 'application/json',
		'css' => 'text/css',
	];

	/**
	 * @var string[]
	 */
	private static $formatHtmlInjects = [
		'js' => '<script src="%path%"></script>',
		'css' => '<link href="%path%" rel="stylesheet">',
	];

	/**
	 * @var string
	 */
	private $wwwDir;

	/**
	 * @var Container
	 */
	private $container;

	/**
	 * @param string $wwwDir
	 * @param Container $container
	 */
	public function __construct(string $wwwDir, Container $container)
	{
		$this->wwwDir = $wwwDir;
		$this->container = $container;
	}

	/**
	 * @param string $route
	 * @return bool
	 */
	public function isAssetsAvailable(string $route): bool
	{
		return $this->getData(trim($route, ':')) !== [];
	}

	/**
	 * @param string $route
	 * @return string
	 */
	public function getHtmlInit(string $route): string
	{
		$return = [];
		$routePath = Helpers::formatRouteToPath($route = trim($route, ':'));

		foreach (array_keys($data = $this->getData($route)) as $format) {
			foreach ($data[$format] ?? [] as $item) {
				if (preg_match('/^((?:https?\:)?\/\/)(.+)$/', $item, $itemParser)) {
					$return[] = str_replace('%path%', ($itemParser[1] === '//' ? 'https://' : '') . $itemParser[2], self::$formatHtmlInjects[$format]);
				}
			}
			if (isset(self::$formatHtmlInjects[$format]) === true) {
				$return[] = str_replace('%path%', Helpers::getBaseUrl() . '/assets/web-loader/' . $routePath . '.' . $format, self::$formatHtmlInjects[$format]);
			}
		}

		return implode("\n", $return);
	}

	/**
	 * @param string $path
	 */
	public function run(string $path): void
	{
		if (preg_match('/^assets\/web-loader\/(.+)$/', $path, $parser)
			&& preg_match('/^(?<module>[a-zA-Z0-9]+)-(?<presenter>[a-zA-Z0-9]+)-(?<action>[a-zA-Z0-9]+)\.(?<format>[a-zA-Z0-9]+)$/', $parser[1], $routeParser)
			&& ($data = $this->getData(Helpers::formatRoute($routeParser['module'], $routeParser['presenter'], $routeParser['action']))) !== []
		) {
			if (isset(self::$formatHeaders[$routeParser['format']]) === true) {
				header('Content-Type: ' . self::$formatHeaders[$routeParser['format']]);
			}

			echo '/* Automatically generated ' . date('Y-m-d H:i:s') . ' */' . "\n\n";

			foreach ($data[$routeParser['format']] ?? [] as $file) {
				echo '/* ' . $file . ' */' . "\n";
				if (is_file($path = $this->wwwDir . '/assets/' . trim($file, '/')) === true) {
					echo file_get_contents($path);
				}

				echo "\n\n";
			}
			die;
		}

		echo '/* empty body */';
		die;
	}

	/**
	 * @param string|null $route
	 * @return string[]|string[][]
	 */
	private function getData(?string $route = null): array
	{
		return \method_exists($this->container, 'getBarajaAssetsLoader')
			? $this->container->getBarajaAssetsLoader($route)
			: [];
	}

}
