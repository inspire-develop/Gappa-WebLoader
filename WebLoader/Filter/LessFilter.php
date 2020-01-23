<?php

declare(strict_types = 1);

namespace WebLoader\Filter;

use lessc;
use WebLoader\Compiler;

/**
 * Less CSS filter
 *
 * @author Jan Marek
 * @license MIT
 */
class LessFilter
{
	/** @var lessc|null */
	private $lc;


	public function __construct(?lessc $lc = null)
	{
		$this->lc = $lc;
	}


	private function getLessC(): lessc
	{
		// lazy loading
		if (empty($this->lc)) {
			$this->lc = new lessc();
		}

		return clone $this->lc;
	}


	/**
	 * Invoke filter
	 */
	public function __invoke(string $code, Compiler $loader, string $file): string
	{
		if (pathinfo($file, PATHINFO_EXTENSION) === 'less') {
			$lessc = $this->getLessC();
			$lessc->importDir = pathinfo($file, PATHINFO_DIRNAME) . '/';
			return $lessc->compile($code);
		}

		return $code;
	}
}
