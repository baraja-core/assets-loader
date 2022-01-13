<?php

declare(strict_types=1);

namespace Baraja\AssetsLoader;


use Baraja\AssetsLoader\Minifier\Minifier;
use Baraja\Url\Url;
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
			'routing' => Expect::arrayOf( // (route [*] => rules)
				Expect::arrayOf( // (rule => definition)
					Expect::anyOf(
						Expect::string()->required(), // URL
						Expect::structure([
							'source' => Expect::string()->required(),
							'format' => Expect::string()->required(),
						])->castTo('array')->required(),
					)->required()->firstIsDefault(),
				),
				Expect::anyOf(Expect::string()->required()),
			),
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
			foreach ($assetFiles as $assetFormat => $assetFile) {
				if (is_array($assetFile)) {
					if (isset($assetFile['format'], $assetFile['source'])) {
						$format = $assetFile['format'];
						$assetFile = $assetFile['source'];
					} else {
						throw new \RuntimeException('Invalid asset structure, expected keys "format" and "source".');
					}
				} elseif (is_string($assetFormat)) {
					if (!preg_match('/^[a-zA-Z0-9]+$/', $assetFile)) {
						throw new \RuntimeException('Invalid asset format for file "' . $assetFormat . '", because "' . $assetFile . '" given.');
					}
					$format = $assetFile;
					$assetFile = $assetFormat;
				} elseif (preg_match('/^(?<name>.+)\.(?<format>[a-zA-Z0-9]+)(?:\?.*)?$/', $assetFile, $fileParser)) {
					$format = $fileParser['format'];
				} else {
					throw new \RuntimeException('Invalid asset filename "' . $assetFile . '". Did you mean "' . $assetFile . '.js"?');
				}
				if (isset($assets[$route][$format]) === false) {
					$assets[$route][$format] = [];
				}
				$assets[$route][$format][] = $assetFile;
			}
		}

		foreach (($config['formatHtmlInjects'] ?? []) as $formatHtmlInject) {
			if (!str_contains($formatHtmlInject, '%path%')) {
				throw new \RuntimeException('HTML inject format must contains variable "%path%", but "' . $formatHtmlInject . '" given.');
			}
		}

		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('minifier'))
			->setFactory(Minifier::class);

		$builder->addDefinition($this->prefix('api'))
			->setFactory(Api::class)
			->setArgument('basePath', rtrim($config['basePath'] ?? $builder->parameters['wwwDir'] . '/assets', '/'))
			->setArgument('data', $assets)
			->setArgument('formatHeaders', array_merge([
				'js' => 'application/javascript',
				'css' => 'text/css',
			], $config['formatHeaders'] ?? []))
			->setArgument('formatHtmlInjects', array_merge([
				'js' => '<script src="%path%"></script>',
				'css' => '<link href="%path%" rel="stylesheet">',
			], $config['formatHtmlInjects'] ?? []));
	}


	public function afterCompile(ClassType $class): void
	{
		if (PHP_SAPI === 'cli') {
			return;
		}
		$class->getMethod('initialize')->addBody(
			'// assets loader.' . "\n"
			. '(function (): void {' . "\n"
			. "\t" . 'if (str_starts_with(' . Url::class . '::get()->getRelativeUrl(), \'assets/web-loader/\')) {' . "\n"
			. "\t\t" . '$this->getByType(' . Application::class . '::class)->onStartup[] = function(' . Application::class . ' $a): void {' . "\n"
			. "\t\t\t" . '$this->getByType(\'' . Api::class . '\')->run();' . "\n"
			. "\t\t" . '};' . "\n"
			. "\t" . '}' . "\n"
			. '})();',
		);
	}


	private function validateRouteFormat(string $route): void
	{
		if ($route === '*') { // special case for global assets
			return;
		}
		if (preg_match('/^[A-Z0-9][A-Za-z0-9]*:(?:\*|[A-Z0-9][A-Za-z0-9]*:(?:\*|[a-z0-9][A-Za-z0-9]*))$/', trim($route, ':')) === 0) {
			throw new AssetLoaderException(
				'Route "' . $route . '" is invalid. '
				. 'Route must be absolute "Module:Presenter:action" or end '
				. 'with dynamic part in format "Module:*" or "Module:Presenter:*".',
			);
		}
	}
}
