<?php

declare(strict_types=1);

namespace Baraja\AssetsLoader;


use Nette\Application\Application;
use Nette\DI\CompilerExtension;
use Nette\PhpGenerator\ClassType;

class LoaderExtension extends CompilerExtension
{

	private const PROPERTY_NAME = 'baraja_assets_loader';

	/**
	 * @param ClassType $class
	 */
	public function afterCompile(ClassType $class): void
	{
		$assets = [];
		$files = $this->getConfig()['routing'] ?? [];

		if (isset($this->getConfig()['base']) === true) {
			$files = array_merge($files, ['base' => $this->getConfig()['base']]);
		}

		foreach ($files as $route => $assetFiles) {
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

		$class->addProperty(self::PROPERTY_NAME, $assets)
			->setVisibility('private');

		$class->addMethod('getBarajaAssetsLoader')
			->setReturnType('array')
			->setBody('return $route === null '
				. '? $this->' . self::PROPERTY_NAME . ' '
				. ': array_merge_recursive($this->' . self::PROPERTY_NAME . '[$route] ?? [], $this->' . self::PROPERTY_NAME . '[\'base\'] ?? []);')
			->addParameter('route', null)
			->setType('string')
			->setNullable(true);

		$class->getMethod('initialize')->addBody(
			'if (strncmp($assetsLoader__basePath = ' . Helpers::class . '::processPath($this->getService(\'http.request\')), \'assets/web-loader/\', 18) === 0) {'
			. "\n\t" . '$this->getByType(' . Application::class . '::class)->onStartup[] = function(' . Application::class . ' $a) use ($assetsLoader__basePath) {'
			. "\n\t\t" . '$this->getByType(\'' . Api::class . '\')->run($assetsLoader__basePath);'
			. "\n\t" . '};'
			. "\n" . '}'
		);
	}

}
