<?php

declare(strict_types=1);

namespace Baraja\AssetsLoader;


final class Api
{
	private string $basePath;

	/** @var mixed[] */
	private array $data;

	/** @var string[] */
	private array $formatHeaders;

	/** @var string[] */
	private array $formatHtmlInjects;


	/**
	 * @param mixed[] $data
	 * @param string[] $formatHeaders
	 * @param string[] $formatHtmlInjects
	 */
	public function __construct(string $basePath, array $data, array $formatHeaders, array $formatHtmlInjects)
	{
		$this->basePath = $basePath;
		$this->data = $data;
		$this->formatHeaders = $formatHeaders;
		$this->formatHtmlInjects = $formatHtmlInjects;
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
					$return[] = str_replace('%path%', ($itemParser[1] === '//' ? 'https://' : '') . $itemParser[2], $this->formatHtmlInjects[$format]);
				}
			}
			if (isset($this->formatHtmlInjects[$format]) === true) {
				$return[] = str_replace('%path%', Helpers::getBaseUrl() . '/assets/web-loader/' . $routePath . '.' . $format, $this->formatHtmlInjects[$format]);
			}
		}

		return implode("\n", $return);
	}


	public function run(string $path): void
	{
		if (preg_match('/^assets\/web-loader\/(.+)$/', $path, $parser)
			&& preg_match('/^(?<module>[a-zA-Z0-9]+)-(?<presenter>[a-zA-Z0-9]+)-(?<action>[a-zA-Z0-9]+)\.(?<format>[a-zA-Z0-9]+)$/', $parser[1], $routeParser)
		) {
			if (isset($this->formatHeaders[$routeParser['format']]) === true) {
				header('Content-Type: ' . $this->formatHeaders[$routeParser['format']]);
			} else {
				throw new \RuntimeException(
					'Content type for format "' . $routeParser['format'] . '" does not exist. '
					. 'Did you mean "' . implode('", "', array_keys($this->formatHeaders)) . '"?'
				);
			}

			echo '/* Path "' . htmlspecialchars($path) . '" was automatically generated ' . date('Y-m-d H:i:s') . ' */' . "\n\n";
			if (($data = $this->findData(Helpers::formatRoute($routeParser['module'], $routeParser['presenter'], $routeParser['action']))) !== []) {
				foreach ($data[$routeParser['format']] ?? [] as $file) {
					echo '/* ' . $file . ' */' . "\n";
					if (is_file($path = $this->basePath . '/' . trim($file, '/')) === true) {
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
	 * @return string[]|string[][]
	 */
	private function findData(?string $route = null): array
	{
		if ($this->data !== [] && $route !== null) {
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
