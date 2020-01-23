<?php

declare(strict_types = 1);

namespace WebLoader\Nette;

use Nette\Application\UI\Control;
use Nette\Utils\Html;
use WebLoader\Compiler;
use WebLoader\File;
use WebLoader\FileCollection;

/**
 * Web loader
 *
 * @author Jan Marek
 * @license MIT
 */
abstract class WebLoader extends Control
{

	/** @var Compiler */
	private $compiler;

	/** @var string */
	private $tempPath;

	/** @var bool */
	private $appendLastModified;


	public function __construct(Compiler $compiler, string $tempPath, bool $appendLastModified)
	{
		parent::__construct();
		$this->compiler = $compiler;
		$this->tempPath = $tempPath;
		$this->appendLastModified = $appendLastModified;
	}


	public function getCompiler(): Compiler
	{
		return $this->compiler;
	}


	public function setCompiler(Compiler $compiler): void
	{
		$this->compiler = $compiler;
	}


	public function getTempPath(): string
	{
		return $this->tempPath;
	}


	public function setTempPath(string $tempPath): void
	{
		$this->tempPath = $tempPath;
	}


	/**
	 * Get html element including generated content
	 */
	abstract public function getElement(string $source): Html;


	/**
	 * Generate compiled file(s) and render link(s)
	 */
	public function render(): void
	{
		$hasArgs = func_num_args() > 0;

		if ($hasArgs) {
			$backup = $this->compiler->getFileCollection();
			$newFiles = new FileCollection($backup->getRoot());
			$newFiles->addFiles(func_get_args());
			$this->compiler->setFileCollection($newFiles);
		}

		// remote files
		foreach ($this->compiler->getFileCollection()->getRemoteFiles() as $file) {
			echo $this->getElement($file), PHP_EOL;
		}

		foreach ($this->compiler->generate() as $file) {
			echo $this->getElement($this->getGeneratedFilePath($file)), PHP_EOL;
		}

		if ($hasArgs && !empty($backup)) {
			$this->compiler->setFileCollection($backup);
		}
	}


	protected function getGeneratedFilePath(File $file): string
	{
		$path = $this->tempPath . '/' . $file->getFile();

		if ($this->appendLastModified) {
			$path .= '?' . $file->getLastModified();
		}

		return $path;
	}
}
