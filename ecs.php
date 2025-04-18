<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Casing\ConstantCaseFixer;
use PhpCsFixer\Fixer\ClassNotation\OrderedClassElementsFixer;
use PhpCsFixer\Fixer\Operator\NotOperatorWithSuccessorSpaceFixer;
use PhpCsFixer\Fixer\Phpdoc\GeneralPhpdocAnnotationRemoveFixer;
use Symplify\CodingStandard\Fixer\LineLength\LineLengthFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Option;

/**
 * https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/blob/master/doc/rules/index.rst
 * https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/blob/master/doc/ruleSets/index.rst
 */

/**
 * https://github.com/slevomat/coding-standard
 */
return ECSConfig
	::configure()
		->withPaths(
			[
				__DIR__ . '/src',
				__DIR__ . '/tests/Replicator',
			]
		)
		->withRootFiles()
		->withParallel()
		->withSpacing(indentation: Option::INDENTATION_TAB, lineEnding: PHP_EOL)
		->withPreparedSets(psr12: true, common: true, symplify: true, cleanCode: true)
		->withSkip([
			GeneralPhpdocAnnotationRemoveFixer::class,
			OrderedClassElementsFixer::class,
			LineLengthFixer::class,
			ConstantCaseFixer::class,
			NotOperatorWithSuccessorSpaceFixer::class,
		])
;
