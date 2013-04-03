<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Replicator\DI;

use Kdyby;
use Nette;
use Nette\Utils\PhpGenerator as Code;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ReplicatorExtension extends Nette\Config\CompilerExtension
{

	public function afterCompile(Code\ClassType $class)
	{
		parent::afterCompile($class);

		$init = $class->methods['initialize'];
		$init->addBody('Kdyby\Replicator\Container::register();');
	}



	/**
	 * @param \Nette\Config\Configurator $configurator
	 */
	public static function register(Nette\Config\Configurator $configurator)
	{
		$configurator->onCompile[] = function ($config, Nette\Config\Compiler $compiler) {
			$compiler->addExtension('formsReplicator', new ReplicatorExtension());
		};
	}

}
