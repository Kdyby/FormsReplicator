<?php declare(strict_types=1);

namespace Webwings\Replicator\DI;

use Nette\Configurator;
use Nette\DI\Compiler;
use Nette\DI\CompilerExtension;
use Nette\Forms\Container;
use Nette\PhpGenerator\ClassType;

/**
 * @author Filip Procházka <filip@prochazka.su>
 * @author Webwings
 */
class ReplicatorExtension extends CompilerExtension
{
    private const DEFAULTS = [
        'methodName' => 'addDynamic',
        'containerClass' => '\Nette\Forms\Container',
    ];

    public function loadConfiguration()
    {
        $this->validateConfig(self::DEFAULTS);
    }

    /**
     * @param ClassType $class
     */
    public function afterCompile(ClassType $class)
    {
        parent::afterCompile($class);

        $init = $class->getMethod('initialize');
        $init->addBody("\Webwings\Replicator\Container::register(?, ?);", [
            $this->config['methodName'],
            $this->config['containerClass'],
        ]);
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
