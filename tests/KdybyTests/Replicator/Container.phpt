<?php

/**
 * Test: Kdyby\Replicator\Container.
 *
 * @testCase Kdyby\Replicator\ContainerTest
 * @author Filip Procházka <filip@prochazka.su>
 * @package Kdyby\Replicator
 */

namespace KdybyTests\Replicator;

use Kdyby\Replicator\Container;
use Nette;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ContainerTest extends Tester\TestCase
{

	public function testFunctionality()
	{
		$replicator = new Container(function (Nette\Forms\Container $container) {
			$container->addText('name', "Name");
		});

		Assert::true($replicator[0]['name'] instanceof Nette\Forms\Controls\TextInput);
		Assert::true($replicator[2]['name'] instanceof Nette\Forms\Controls\TextInput);
		Assert::true($replicator[1000]['name'] instanceof Nette\Forms\Controls\TextInput);
	}

}

\run(new ContainerTest());
