<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Replicator\DI;

use Kdyby\Replicator\Container;
use Nette;

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ReplicatorExtension extends Nette\DI\CompilerExtension
{
	public function afterCompile(Nette\PhpGenerator\ClassType $class): void
	{
		parent::afterCompile($class);

		$init = $class->getMethod('initialize');
		$init->addBody(Container::class . '::register();');
	}

	public static function register(Nette\Configurator $configurator): void
	{
		$configurator->onCompile[] = function ($config, Nette\DI\Compiler $compiler) {
			$compiler->addExtension('formsReplicator', new ReplicatorExtension());
		};
	}
}
