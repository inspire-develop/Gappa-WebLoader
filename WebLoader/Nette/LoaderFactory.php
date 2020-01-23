<?php

declare(strict_types = 1);

namespace WebLoader\Nette;

use Nette\DI\Container;
use Nette\Http\IRequest;
use WebLoader\Compiler;

class LoaderFactory
{

	/** @var \Nette\Http\IRequest */
	private $httpRequest;

	/** @var \Nette\DI\Container */
	private $serviceLocator;

	/** @var array */
	private $tempPaths;

	/** @var string */
	private $extensionName;


	public function __construct(array $tempPaths, string $extensionName, IRequest $httpRequest, Container $serviceLocator)
	{
		$this->httpRequest = $httpRequest;
		$this->serviceLocator = $serviceLocator;
		$this->tempPaths = $tempPaths;
		$this->extensionName = $extensionName;
	}


	public function createCssLoader(string $name, bool $appendLastModified = false): CssLoader
	{
		/** @var Compiler $compiler */
		$compiler = $this->serviceLocator->getService($this->extensionName . '.css' . ucfirst($name) . 'Compiler');
		return new CssLoader($compiler, $this->formatTempPath($name, $compiler->isAbsoluteUrl()), $appendLastModified);
	}


	public function createJavaScriptLoader(string $name, bool $appendLastModified = false): JavaScriptLoader
	{
		/** @var Compiler $compiler */
		$compiler = $this->serviceLocator->getService($this->extensionName . '.js' . ucfirst($name) . 'Compiler');
		return new JavaScriptLoader($compiler, $this->formatTempPath($name, $compiler->isAbsoluteUrl()), $appendLastModified);
	}


	private function formatTempPath(string $name, bool $absoluteUrl = false): string
	{
		$lName = strtolower($name);
		$tempPath = isset($this->tempPaths[$lName]) ? $this->tempPaths[$lName] : Extension::DEFAULT_TEMP_PATH;
		$method = $absoluteUrl ? 'getBaseUrl' : 'getBasePath';
		return rtrim($this->httpRequest->getUrl()->{$method}(), '/') . '/' . $tempPath;
	}
}
