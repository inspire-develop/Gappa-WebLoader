<?php

declare(strict_types = 1);

namespace WebLoader\Nette\SymfonyConsole;

use Nette\DI\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use WebLoader\Compiler;
use WebLoader\File;

/**
 * Generate Command
 */
class GenerateCommand extends Command
{
	/** @var string */
	protected static $defaultName = 'webloader:generate';

	/** @var Compiler[] */
	private $compilers = [];


	public function __construct(Container $container)
	{
		parent::__construct();

		$compilers = $container->findByType(Compiler::class);
		foreach ($compilers as $compilerName) {
			$this->compilers[$compilerName] = $container->getService($compilerName);
		}
	}


	protected function configure(): void
	{
		$this->setName(self::$defaultName)
			->setDescription('Generates files.')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Generate if not modified.');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int|void|null
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		// $force = $input->getOption('force');

		$nofiles = true;
		foreach ($this->compilers as $compiler) {
			// $files = $compiler->generate(!$force);
			$files = $compiler->generate();

			/** @var File $file */
			foreach ($files as $file) {
				$output->writeln($file->getFile());
				$nofiles = false;
			}
		}

		if ($nofiles) {
			$output->writeln('No files generated.');
		}
	}
}
