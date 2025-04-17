<?php

/**
 * Test: Kdyby\Replicator\Extension.
 *
 * @testCase KdybyTests\Replicator\ExtensionTest
 * @author   Filip Procházka <filip@prochazka.su>
 * @package  Kdyby\Replicator
 */

namespace KdybyTests\Replicator;

use Nette;
use Tester\Environment;
use Tester\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ExtensionTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		Environment::$checkAssertions = FALSE;
	}

	public function testExtensionMethodIsRegistered(): void
	{
		Helper::createContainer();

		$form = new Nette\Forms\Form();
		$form->addDynamic('people', function () {
		});
	}
}

(new ExtensionTest())->run();
