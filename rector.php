<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

/**
 * https://getrector.com/find-rule
 */
return RectorConfig
	::configure()
		->withPaths(
			[
				__DIR__ . '/src',
				__DIR__ . '/tests/Replicator',
			]
		)
		->withRootFiles()
		->withParallel()
		->withPhpSets(
			php82: true,
		)
;
