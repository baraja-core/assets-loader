<?php

declare(strict_types=1);

namespace Baraja\AssetsLoader;


final class Api
{
	/** @var string[] */
	private static array $formatHeaders = [
		'js' => 'application/json',
		'css' => 'text/css',
	];

	/** @var string[] */
	private static array $formatHtmlInjects = [
		'js' => '<script src="%path%"></script>',
		'css' => '<link href="%path%" rel="stylesheet">',
	];

	private string $wwwDir;

	/** @var mixed[]|null */
	private ?array $data;


	public function __construct(string $wwwDir)
	{
		$this->wwwDir = $wwwDir;
	}


	public function isAssetsAvailable(string $route): bool
	{
		return $this->findData(trim($route, ':')) !== [];
	}


	public function getHtmlInit(string $route): string
	{
		$routePath = Helpers::formatRouteToPath($route = trim($route, ':'));

		$return = [];
		foreach (array_keys($data = $this->findData($route)) as $format) {
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


	public function run(string $path): void
	{
		if (preg_match('/^assets\/web-loader\/(.+)$/', $path, $parser)
			&& preg_match('/^(?<module>[a-zA-Z0-9]+)-(?<presenter>[a-zA-Z0-9]+)-(?<action>[a-zA-Z0-9]+)\.(?<format>[a-zA-Z0-9]+)$/', $parser[1], $routeParser)
		) {
			if (isset(self::$formatHeaders[$routeParser['format']]) === true) {
				header('Content-Type: ' . self::$formatHeaders[$routeParser['format']]);
			} else {
				throw new \RuntimeException(
					'Content type for format "' . $routeParser['format'] . '" does not exist. '
					. 'Did you mean "' . implode('", "', array_keys(self::$formatHeaders)) . '"?'
				);
			}

			echo '/* Path "' . htmlspecialchars($path) . '" was automatically generated ' . date('Y-m-d H:i:s') . ' */' . "\n\n";
			if (($data = $this->findData(Helpers::formatRoute($routeParser['module'], $routeParser['presenter'], $routeParser['action']))) !== []) {
				foreach ($data[$routeParser['format']] ?? [] as $file) {
					echo '/* ' . $file . ' */' . "\n";
					if (is_file($path = $this->wwwDir . '/assets/' . trim($file, '/')) === true) {
						echo file_get_contents($path);
					}
					echo "\n\n";
				}
				die;
			}
		}

		echo '/* empty body */';
		die;
	}


	/**
	 * @param mixed[] $data
	 * @internal used by DIC.
	 */
	public function setData(array $data): void
	{
		$this->data = $data;
	}


	/**
	 * @return string[]|string[][]
	 */
	private function findData(?string $route = null): array
	{
		if ($this->data === null) {
			AssetLoaderException::dataIsEmpty();
		}
		if ($route !== null) {
			$selectors = [];
			if (preg_match('/^([^:]+):([^:]+):([^:]+)$/', trim($route, ':'), $routeParser)) {
				$selectors[] = '*';
				$selectors[] = $routeParser[1] . ':*';
				$selectors[] = $routeParser[1] . ':' . $routeParser[2] . ':*';
				$selectors[] = $routeParser[1] . ':' . $routeParser[2] . ':' . $routeParser[3];
			} else {
				AssetLoaderException::routeIsInInvalidFormat($route);
			}

			$return = [];
			foreach ($selectors as $selector) {
				$return[] = $this->data[$selector] ?? [];
			}

			return array_merge_recursive([], ...$return);
		}

		return $this->data;
	}
}
