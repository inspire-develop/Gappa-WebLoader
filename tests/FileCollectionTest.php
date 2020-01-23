<?php
declare(strict_types=1);

namespace WebLoader\Test;

use PHPUnit\Framework\TestCase;
use WebLoader\FileCollection;

/**
 * FileCollection test
 *
 * @author Jan Marek
 */
class FileCollectionTest extends TestCase
{

	/** @var FileCollection */
	private $object;


	protected function setUp(): void
	{
		$this->object = new FileCollection(__DIR__ . '/fixtures');
	}


	public function testAddGetFiles(): void
	{
		$this->object->addFile('a.txt');
		$this->object->addFile(__DIR__ . '/fixtures/a.txt');
		$this->object->addFile(__DIR__ . '/fixtures/b.txt');
		$this->object->addFiles([__DIR__ . '/fixtures/c.txt']);
		$expected = [
			__DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'a.txt',
			__DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'b.txt',
			__DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'c.txt',
		];
		$this->assertEqualPaths($expected, $this->object->getFiles());
	}


	/**
	 * @expectedException \Webloader\FileNotFoundException
	 */
	public function testAddNonExistingFile()
	{
		$this->object->addFile('sdfsdg.txt');
	}


	public function testRemoveFile()
	{
		$this->object->addFile(__DIR__ . '/fixtures/a.txt');
		$this->object->addFile(__DIR__ . '/fixtures/b.txt');

		$this->object->removeFile('a.txt');
		$expected = [
			__DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'b.txt',
		];
		$this->assertEqualPaths($expected, $this->object->getFiles());

		$this->object->removeFiles([__DIR__ . '/fixtures/b.txt']);
	}


	public function testCannonicalizePath()
	{
		$abs = __DIR__ . '/./fixtures/a.txt';
		$rel = 'a.txt';
		$expected = __DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'a.txt';

		$this->assertEqualPaths($expected, $this->object->cannonicalizePath($abs));
		$this->assertEqualPaths($expected, $this->object->cannonicalizePath($rel));

		try {
			$this->object->cannonicalizePath('nesdagf');
			$this->fail('Exception was not thrown.');
		} catch (\WebLoader\FileNotFoundException $e) {
		}
	}


	public function testClear()
	{
		$this->object->addFile('a.txt');
		$this->object->addRemoteFile('http://jquery.com/jquery.js');
		$this->object->addWatchFile('b.txt');
		$this->object->clear();

		$this->assertEquals([], $this->object->getFiles());
		$this->assertEquals([], $this->object->getRemoteFiles());
		$this->assertEquals([], $this->object->getWatchFiles());
	}


	public function testRemoteFiles()
	{
		$this->object->addRemoteFile('http://jquery.com/jquery.js');
		$this->object->addRemoteFiles([
			'http://jquery.com/jquery.js',
			'http://google.com/angular.js',
		]);

		$expected = [
			'http://jquery.com/jquery.js',
			'http://google.com/angular.js',
		];
		$this->assertEquals($expected, $this->object->getRemoteFiles());
	}


	public function testWatchFiles()
	{
		$this->object->addWatchFile(__DIR__ . '/fixtures/a.txt');
		$this->object->addWatchFile(__DIR__ . '/fixtures/b.txt');
		$this->object->addWatchFiles([__DIR__ . '/fixtures/c.txt']);
		$expected = [
			__DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'a.txt',
			__DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'b.txt',
			__DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'c.txt',
		];
		$this->assertEqualPaths($expected, $this->object->getWatchFiles());
	}


	public function testTraversableFiles()
	{
		$this->object->addFiles(new \ArrayIterator(['a.txt']));
		$this->assertEquals(1, count($this->object->getFiles()));
	}


	public function testTraversableRemoteFiles()
	{
		$this->object->addRemoteFiles(new \ArrayIterator(['http://jquery.com/jquery.js']));
		$this->assertEquals(1, count($this->object->getRemoteFiles()));
	}


	public function testSplFileInfo()
	{
		$this->object->addFile((string) new \SplFileInfo(__DIR__ . '/fixtures/a.txt'));
		$this->assertEquals(1, count($this->object->getFiles()));
	}


	private function assertEqualPaths($expected, $actual)
	{
		$actual = (array) $actual;
		foreach ((array) $expected as $key => $path) {
			$this->assertTrue(isset($actual[$key]));
			$this->assertEquals(\WebLoader\Path::normalize($path), \WebLoader\Path::normalize($actual[$key]));
		}
	}
}
