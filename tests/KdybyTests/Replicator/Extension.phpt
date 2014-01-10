<?php

/**
 * Test: Kdyby\Replicator\Extension.
 *
 * @testCase KdybyTests\Replicator\ExtensionTest
 * @author Filip Procházka <filip@prochazka.su>
 * @package Kdyby\Replicator
 */

namespace KdybyTests\Replicator;

use Kdyby;
use Nette;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ExtensionTest extends Tester\TestCase
{

	/**
	 * @return \SystemContainer|\Nette\DI\Container
	 */
	protected function createContainer()
	{
		$config = new Nette\Configurator();
		$config->setTempDirectory(TEMP_DIR);
		Kdyby\Replicator\DI\ReplicatorExtension::register($config);

		return $config->createContainer();
	}



	public function testExtensionMethodIsRegistered()
	{
		$this->createContainer(); // initialize

		$form = new Nette\Forms\Form();
		$form->addDynamic('people', function () {});
	}

}

\run(new ExtensionTest());
