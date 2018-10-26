<?php declare(strict_types=1);

namespace Webwings\Replicator\DI;

use Nette\Configurator;
use Nette\DI\Compiler;
use Nette\DI\CompilerExtension;
use Nette\PhpGenerator\ClassType;

/**
 * @author Filip Procházka <filip@prochazka.su>
 * @author Webwings
 */
class ReplicatorExtension extends CompilerExtension
{

    /**
     * @param ClassType $class
     */
    public function afterCompile(ClassType $class)
    {
        parent::afterCompile($class);

        $init = $class->getMethod('initialize');
        $init->addBody('\Webwings\Replicator\Container::register();');
    }


    /**
     * @param \Nette\Configurator $configurator
     */
    public static function register(Configurator $configurator)
    {
        $configurator->onCompile[] = function ($config, Compiler $compiler) {
            $compiler->addExtension('formsReplicator', new ReplicatorExtension());
        };
    }

}
