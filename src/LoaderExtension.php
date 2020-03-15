<?php

declare(strict_types=1);

namespace Baraja\AssetsLoader;


use Nette\Application\Application;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\PhpGenerator\ClassType;

final class LoaderExtension extends CompilerExtension
{

	public function beforeCompile(): void
	{
		$assets = [];
		$files = $this->getConfig()['routing'] ?? [];

		if (isset($this->getConfig()['base']) === true) {
			$files = array_merge_recursive($files, ['*' => $this->getConfig()['base']]);
		}

		foreach ($files as $route => $assetFiles) {
			$this->validateRouteFormat($route);
			foreach ($assetFiles as $assetFile) {
				if (preg_match('/^(?<name>.+)\.(?<format>[a-zA-Z0-9]+)$/', $assetFile, $fileParser)) {
					if (isset($assets[$route][$fileParser['format']]) === false) {
						$assets[$route][$fileParser['format']] = [];
					}

					$assets[$route][$fileParser['format']][] = $assetFile;
				} else {
					AssetLoaderException::invalidFileName($assetFile);
				}
			}
		}

		/** @var ServiceDefinition $definition */
		$definition = $this->getContainerBuilder()->getDefinitionByType(Api::class);
		$definition->addSetup('?->setData(?)', ['@self', $assets]);
	}

	/**
	 * @param ClassType $class
	 */
	public function afterCompile(ClassType $class): void
	{
		$class->getMethod('initialize')->addBody(
			'if (strncmp($assetsLoader__basePath = ' . Helpers::class . '::processPath($this->getService(\'http.request\')), \'assets/web-loader/\', 18) === 0) {'
			. "\n\t" . '$this->getByType(' . Application::class . '::class)->onStartup[] = function(' . Application::class . ' $a) use ($assetsLoader__basePath) {'
			. "\n\t\t" . '$this->getByType(\'' . Api::class . '\')->run($assetsLoader__basePath);'
			. "\n\t" . '};'
			. "\n" . '}'
		);
	}

	/**
	 * @param string $route
	 */
	private function validateRouteFormat(string $route): void
	{
		if ($route === '*') { // special case for global assets
			return;
		}

		if (preg_match('/^[A-Z0-9][A-Za-z0-9]*:(?:\*|[A-Z0-9][A-Za-z0-9]*:(?:\*|[a-z0-9][A-Za-z0-9]*))$/', trim($route, ':')) === 0) {
			AssetLoaderException::routeIsInInvalidFormat($route);
		}
	}

}
