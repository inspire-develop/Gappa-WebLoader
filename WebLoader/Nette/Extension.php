<?php

declare(strict_types = 1);

namespace WebLoader\Nette;

use Nette\Configurator;
use Nette\DI\Compiler;
use Nette\DI\CompilerExtension;
use Nette\DI\Config\Helpers;
use Nette\DI\ContainerBuilder;
use Nette\PhpGenerator\ClassType;
use Nette\Utils\Finder;
use SplFileInfo;
use WebLoader\FileNotFoundException;

/**
 * @author Jan Marek
 */
class Extension extends CompilerExtension
{
	/** @var string */
	public const DEFAULT_TEMP_PATH = 'webtemp';

	/** @var string */
	public const EXTENSION_NAME = 'webloader';


	public function getDefaultConfig(): array
	{
		return [
			'jsDefaults' => [
				'checkLastModified' => true,
				'debug' => false,
				'sourceDir' => '%wwwDir%/js',
				'tempDir' => '%wwwDir%/' . self::DEFAULT_TEMP_PATH,
				'tempPath' => self::DEFAULT_TEMP_PATH,
				'files' => [],
				'watchFiles' => [],
				'remoteFiles' => [],
				'filters' => [],
				'fileFilters' => [],
				'joinFiles' => true,
				'async' => false,
				'defer' => false,
				'nonce' => null,
				'absoluteUrl' => false,
				'namingConvention' => '@' . $this->prefix('jsNamingConvention'),
			],
			'cssDefaults' => [
				'checkLastModified' => true,
				'debug' => false,
				'sourceDir' => '%wwwDir%/css',
				'tempDir' => '%wwwDir%/' . self::DEFAULT_TEMP_PATH,
				'tempPath' => self::DEFAULT_TEMP_PATH,
				'files' => [],
				'watchFiles' => [],
				'remoteFiles' => [],
				'filters' => [],
				'fileFilters' => [],
				'joinFiles' => true,
				'async' => false,
				'defer' => false,
				'nonce' => null,
				'absoluteUrl' => false,
				'namingConvention' => '@' . $this->prefix('cssNamingConvention'),
			],
			'js' => [],
			'css' => [],
			'debugger' => '%debugMode%',
		];
	}


	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig($this->getDefaultConfig());

		$builder->addDefinition($this->prefix('cssNamingConvention'))
			->setFactory('WebLoader\DefaultOutputNamingConvention::createCssConvention');

		$builder->addDefinition($this->prefix('jsNamingConvention'))
			->setFactory('WebLoader\DefaultOutputNamingConvention::createJsConvention');

		if ($config['debugger']) {
			$builder->addDefinition($this->prefix('tracyPanel'))
				->setClass('WebLoader\Nette\Diagnostics\Panel')
				->setArguments([$builder->expand('%appDir%')]);
		}

		$builder->parameters['webloader'] = $config;

		$loaderFactoryTempPaths = [];

		foreach (['css', 'js'] as $type) {
			foreach ($config[$type] as $name => $wlConfig) {
				/** @var array $wlConfig */
				$wlConfig = Helpers::merge($wlConfig, $config[$type . 'Defaults']);
				$this->addWebLoader($builder, $type . ucfirst($name), $wlConfig);
				$loaderFactoryTempPaths[strtolower($name)] = $wlConfig['tempPath'];

				if (!is_dir($wlConfig['tempDir']) || !is_writable($wlConfig['tempDir'])) {
					throw new CompilationException(sprintf("You must create a writable directory '%s'", $wlConfig['tempDir']));
				}
			}
		}

		$builder->addDefinition($this->prefix('factory'))
			->setClass('WebLoader\Nette\LoaderFactory', [$loaderFactoryTempPaths, $this->name]);

		if (class_exists('Symfony\Component\Console\Command\Command')) {
			$builder->addDefinition($this->prefix('generateCommand'))
				->setClass('WebLoader\Nette\SymfonyConsole\GenerateCommand')
				->addTag('kdyby.console.command');
		}
	}


	private function addWebLoader(ContainerBuilder $builder, string $name, array $config): void
	{
		$filesServiceName = $this->prefix($name . 'Files');

		$files = $builder->addDefinition($filesServiceName)
			->setClass('WebLoader\FileCollection')
			->setArguments([$config['sourceDir']]);

		foreach ($this->findFiles($config['files'], $config['sourceDir']) as $file) {
			$files->addSetup('addFile', [$file]);
		}

		foreach ($this->findFiles($config['watchFiles'], $config['sourceDir']) as $file) {
			$files->addSetup('addWatchFile', [$file]);
		}

		$files->addSetup('addRemoteFiles', [$config['remoteFiles']]);

		$compiler = $builder->addDefinition($this->prefix($name . 'Compiler'))
			->setClass('WebLoader\Compiler')
			->setArguments([
				'@' . $filesServiceName,
				$config['namingConvention'],
				$config['tempDir'],
			]);

		$compiler
			->addSetup('setJoinFiles', [$config['joinFiles']])
			->addSetup('setAsync', [$config['async']])
			->addSetup('setDefer', [$config['defer']])
			->addSetup('setNonce', [$config['nonce']])
			->addSetup('setAbsoluteUrl', [$config['absoluteUrl']]);

		if ($builder->parameters['webloader']['debugger']) {
			$compiler->addSetup('@' . $this->prefix('tracyPanel') . '::addLoader', [
				$name,
				'@' . $this->prefix($name . 'Compiler'),
			]);
		}

		foreach ($config['filters'] as $filter) {
			$compiler->addSetup('addFilter', [$filter]);
		}

		foreach ($config['fileFilters'] as $filter) {
			$compiler->addSetup('addFileFilter', [$filter]);
		}

		if (isset($config['debug']) && $config['debug']) {
			$compiler->addSetup('enableDebugging');
		}

		$compiler->addSetup('setCheckLastModified', [$config['checkLastModified']]);

		// todo css media
	}


	public function afterCompile(ClassType $class): void
	{
		$meta = $class->getProperty('meta');
		if (array_key_exists('webloader\\nette\\loaderfactory', $meta->value['types'])) {
			$meta->value['types']['webloader\\loaderfactory'] = $meta->value['types']['webloader\\nette\\loaderfactory'];
		}
		if (array_key_exists('WebLoader\\Nette\\LoaderFactory', $meta->value['types'])) {
			$meta->value['types']['WebLoader\\LoaderFactory'] = $meta->value['types']['WebLoader\\Nette\\LoaderFactory'];
		}

		$init = $class->methods['initialize'];
		$init->addBody('if (!class_exists(?, ?)) class_alias(?, ?);', ['WebLoader\\LoaderFactory', false, 'WebLoader\\Nette\\LoaderFactory', 'WebLoader\\LoaderFactory']);
	}


	public function install(Configurator $configurator): void
	{
		$self = $this;
		$configurator->onCompile[] = function ($configurator, Compiler $compiler) use ($self): void {
			$compiler->addExtension($self::EXTENSION_NAME, $self);
		};
	}


	private function findFiles(array $filesConfig, string $sourceDir): array
	{
		$normalizedFiles = [];

		/** @var array|string $file */
		foreach ($filesConfig as $file) {
			// finder support
			if (is_array($file) && isset($file['files']) && (isset($file['in']) || isset($file['from']))) {
				$finder = Finder::findFiles($file['files']);

				if (isset($file['exclude'])) {
					$finder->exclude($file['exclude']);
				}

				if (isset($file['in'])) {
					$finder->in(is_dir($file['in']) ? $file['in'] : $sourceDir . DIRECTORY_SEPARATOR . $file['in']);
				} else {
					$finder->from(is_dir($file['from']) ? $file['from'] : $sourceDir . DIRECTORY_SEPARATOR . $file['from']);
				}

				$foundFilesList = [];
				foreach ($finder as $foundFile) {
					/** @var SplFileInfo $foundFile */
					$foundFilesList[] = $foundFile->getPathname();
				}

				natsort($foundFilesList);

				/** @var string $foundFilePathname */
				foreach ($foundFilesList as $foundFilePathname) {
					$normalizedFiles[] = $foundFilePathname;
				}

			} else {
				if (is_string($file)) {
					$this->checkFileExists($file, $sourceDir);
					$normalizedFiles[] = $file;
				}
			}
		}

		return $normalizedFiles;
	}


	protected function checkFileExists(string $file, string $sourceDir): void
	{
		if (!$this->fileExists($file)) {
			$tmp = rtrim($sourceDir, '/\\') . DIRECTORY_SEPARATOR . $file;
			if (!$this->fileExists($tmp)) {
				throw new FileNotFoundException(sprintf("Neither '%s' or '%s' was found", $file, $tmp));
			}
		}
	}


	/**
	 * Some servers seem to have problems under cron user with open_basedir restriction when using relative paths
	 */
	protected function fileExists(string $file): bool
	{
		$file = realpath($file);

		if ($file === false) {
			$file = '';
		}

		return file_exists($file);
	}
}

