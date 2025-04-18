<?php

namespace KdybyTests\Replicator;

use Kdyby\Replicator\DI\ReplicatorExtension;
use Nette;

class Helper
{
	public static function createContainer(): Nette\DI\Container
	{
		$config = new Nette\Configurator();
		$config->setTempDirectory(TEMP_DIR);
		$config->addConfig(__DIR__ . '/config.neon');
		ReplicatorExtension::register($config);

		return $config->createContainer();
	}
}
