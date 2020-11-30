<?php

declare(strict_types=1);

namespace Baraja\AssetsLoader;


use Baraja\AssetsLoader\Minifier\Minifier;

final class Api
{
	private string $basePath;

	/** @var mixed[] */
	private array $data;

	/** @var string[] */
	private array $formatHeaders;

	/** @var string[] */
	private array $formatHtmlInjects;

	private Minifier $minifier;


	/**
	 * @param mixed[] $data
	 * @param string[] $formatHeaders
	 * @param string[] $formatHtmlInjects
	 */
	public function __construct(string $basePath, array $data, array $formatHeaders, array $formatHtmlInjects, Minifier $minifier)
	{
		$this->basePath = $basePath;
		$this->data = $data;
		$this->formatHeaders = $formatHeaders;
		$this->formatHtmlInjects = $formatHtmlInjects;
		$this->minifier = $minifier;
	}


	public function isAssetsAvailable(string $route): bool
	{
		return $this->findGlobalData($route = trim($route, ':')) !== [] || $this->findLocalData($route) !== [];
	}


	public function getHtmlInit(string $route): string
	{
		$routePath = Helpers::formatRouteToPath($route = trim($route, ':'));

		return implode("\n", array_merge(
			$this->renderInjectTagsByData('global-' . preg_replace('/^([^-]+)-(.*)$/', '$1', $routePath), $this->findGlobalData($route)),
			$this->renderInjectTagsByData($routePath, $this->findLocalData($route))
		));
	}


	/**
	 * Render real assets data to HTTP response.
	 *
	 * 1. Route current HTTP query
	 * 2. Set HTTP header (simulate real file response)
	 * 3. Loop all available data and count last modification date
	 * 4. Render welcome information header + return minified haystack
	 */
	public function run(string $path): void
	{
		if (preg_match('/^assets\/web-loader\/(.+?)(?:\?v=[0-9a-f]{6})?$/', $path, $parser)) { // 1.
			if (preg_match('/^global-(?<module>[a-zA-Z0-9]+)\.(?<format>[a-zA-Z0-9]+)$/', $parser[1], $globalRouteParser)) {
				$format = $globalRouteParser['format'];
				$data = $this->findGlobalData($globalRouteParser['module'] . ':Homepage:default');
			} elseif (preg_match('/^(?<module>[a-zA-Z0-9]+)-(?<presenter>[a-zA-Z0-9]+)-(?<action>[a-zA-Z0-9]+)\.(?<format>[a-zA-Z0-9]+)$/', $parser[1], $routeParser)) {
				$format = $routeParser['format'];
				$data = $this->findLocalData(Helpers::formatRoute($routeParser['module'], $routeParser['presenter'], $routeParser['action']));
			} else {
				echo '/* empty body */';
				die;
			}
			if (isset($this->formatHeaders[$format]) === true) { // 2.
				header('Content-Type: ' . $this->formatHeaders[$format]);
			} else {
				throw new \RuntimeException(
					'Content type for format "' . $format . '" does not exist. '
					. 'Did you mean "' . implode('", "', array_keys($this->formatHeaders)) . '"?'
				);
			}

			$filePaths = [];
			$topModTime = 0;
			if ($data !== []) { // 3.
				foreach ($data[$format] ?? [] as $file) {
					if (is_file($filePath = $this->basePath . '/' . trim($file, '/')) === true) {
						if (($modificationTime = (int) filemtime($filePath)) > 0 && $modificationTime > $topModTime) {
							$topModTime = $modificationTime;
						}
						$filePaths[$file] = $filePath;
					}
				}
			}
			$topModTime = $topModTime ?: time();

			$tsString = gmdate('D, d M Y H:i:s ', $topModTime) . 'GMT';
			$etag = 'EN' . $topModTime;

			if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag && ($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '') === $tsString) {
				header('HTTP/1.1 304 Not Modified');
				die;
			}
			header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 86400)); // 1 day
			header('Last-Modified: ' . $tsString);
			header('ETag: "' . md5($etag) . '"');

			echo '/* Path "' . htmlspecialchars($parser[1]) . '" was automatically generated ' . date('Y-m-d H:i:s', $topModTime) . ' */' . "\n\n"; // 4.
			foreach ($filePaths as $file => $filePath) {
				echo '/* ' . $file . ' */' . "\n";
				echo $this->minifier->minify(file_get_contents($filePath), $format);
				echo "\n\n";
			}
			die;
		}
	}


	/**
	 * @param string[][] $data
	 * @return string[]
	 */
	private function renderInjectTagsByData(string $route, array $data): array
	{
		$return = [];
		foreach (array_keys($data) as $format) {
			$topModTime = 0;
			foreach ($data[$format] ?? [] as $item) {
				if (preg_match('/^((?:https?:)?\/\/)(.+)$/', $item, $itemParser)) {
					$return[] = str_replace('%path%', ($itemParser[1] === '//' ? 'https://' : '') . $itemParser[2], $this->formatHtmlInjects[$format]);
				} elseif (is_file($filePath = $this->basePath . '/' . trim($item, '/')) === true && ($modificationTime = (int) filemtime($filePath)) > 0 && $modificationTime > $topModTime) {
					$topModTime = $modificationTime;
				}
			}
			if (isset($this->formatHtmlInjects[$format]) === true) {
				$return[] = str_replace(
					'%path%',
					Helpers::getBaseUrl() . '/assets/web-loader/' . $route . '.' . $format
					. ($topModTime > 0 ? '?v=' . substr(md5((string) $topModTime), 0, 6) : ''),
					$this->formatHtmlInjects[$format]
				);
			}
		}

		return $return;
	}


	/**
	 * @return string[][]
	 */
	private function findLocalData(string $route): array
	{
		if ($this->data !== []) {
			$routeParser = $this->parseRoute($route);

			return $this->findDataBySelectors([
				$routeParser['module'] . ':' . $routeParser['presenter'] . ':*',
				$routeParser['module'] . ':' . $routeParser['presenter'] . ':' . $routeParser['action'],
			]);
		}

		return $this->data;
	}


	/**
	 * @return string[][]
	 */
	private function findGlobalData(string $route): array
	{
		if ($this->data !== []) {
			return $this->findDataBySelectors([
				'*',
				$this->parseRoute($route)['module'] . ':*',
			]);
		}

		return [];
	}


	/**
	 * @param string[] $selectors
	 * @return string[][]
	 */
	private function findDataBySelectors(array $selectors): array
	{
		$selectors = array_map(fn (string $item): string => trim($item, ':'), $selectors);
		$return = [];
		foreach (array_unique($selectors) as $selector) {
			$return[] = $this->data[$selector] ?? [];
		}

		return array_merge_recursive([], ...$return);
	}


	/**
	 * @return string[]
	 */
	private function parseRoute(string $route): array
	{
		if ($route === 'Error4xx:default') {
			return [
				'module' => '',
				'presenter' => 'Error4xx',
				'action' => 'default',
			];
		}
		if (preg_match('/^(?<module>[^:]+):(?<presenter>[^:]+):(?<action>[^:]+)$/', trim($route, ':'), $routeParser)) {
			return $routeParser;
		}

		throw new AssetLoaderException(
			'Route "' . $route . '" is invalid. '
			. 'Route must be absolute "Module:Presenter:action" or end '
			. 'with dynamic part in format "Module:*" or "Module:Presenter:*".'
		);
	}
}
