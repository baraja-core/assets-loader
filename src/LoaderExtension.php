<?php

declare(strict_types=1);

namespace Baraja\AssetsLoader;


use Nette\Application\Application;
use Nette\DI\CompilerExtension;
use Nette\PhpGenerator\ClassType;
use Nette\Schema\Expect;
use Nette\Schema\Schema;

final class LoaderExtension extends CompilerExtension
{
	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'basePath' => Expect::string(),
			'routing' => Expect::arrayOf(Expect::arrayOf(Expect::string()->required())),
			'base' => Expect::array(),
			'formatHeaders' => Expect::arrayOf(Expect::string()->required()), // 'css' => 'text/css'
			'formatHtmlInjects' => Expect::arrayOf(Expect::string()->required()), // 'css' => '<link href="%path%" rel="stylesheet">'
		])->castTo('array');
	}


	public function beforeCompile(): void
	{
		/** @var mixed[] $config */
		$config = $this->getConfig();
		$files = $config['routing'] ?? [];

		if ($config['base'] !== []) {
			$files = array_merge_recursive($files, ['*' => $config['base']]);
		}

		$assets = [];
		foreach ($files as $route => $assetFiles) {
			$this->validateRouteFormat($route);
			foreach ($assetFiles as $assetFile) {
				if (preg_match('/^(?<name>.+)\.(?<format>[a-zA-Z0-9]+)$/', $assetFile, $fileParser)) {
					if (isset($assets[$route][$fileParser['format']]) === false) {
						$assets[$route][$fileParser['format']] = [];
					}

					$assets[$route][$fileParser['format']][] = $assetFile;
				} else {
					throw new \RuntimeException('Invalid asset filename "' . $assetFile . '". Did you mean "' . $assetFile . '.js"?');
				}
			}
		}

		foreach (($config['formatHtmlInjects'] ?? []) as $formatHtmlInject) {
			if (strpos($formatHtmlInject, '%path%') === false) {
				throw new \RuntimeException('HTML inject format must contains variable "%path%", but "' . $formatHtmlInject . '" given.');
			}
		}

		$builder = $this->getContainerBuilder();
		$builder->addDefinition($this->prefix('api'))
			->setFactory(Api::class)
			->setArgument('basePath', rtrim($config['basePath'] ?? $builder->parameters['wwwDir'] . '/assets', '/'))
			->setArgument('data', $assets)
			->setArgument('formatHeaders', array_merge([
				'js' => 'application/json',
				'css' => 'text/css',
			], $config['formatHeaders'] ?? []))
			->setArgument('formatHtmlInjects', array_merge([
				'js' => '<script src="%path%"></script>',
				'css' => '<link href="%path%" rel="stylesheet">',
			], $config['formatHtmlInjects'] ?? []));
	}


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
