<?php declare(strict_types=1);

namespace Webwings\Replicator;

use Nette\Application\Request;
use Nette\Application\UI\Presenter;
use Nette\ComponentModel\IComponent;
use Nette\Forms\Controls\BaseControl;
use Nette\Forms\Controls\SubmitButton;
use Nette\Forms\Form;
use Nette\Application\UI;
use Nette\InvalidArgumentException;
use Nette\Utils\Callback;

/**
 * @author Filip Procházka <filip@prochazka.su>
 * @author Jan Tvrdík
 * @author Webwings
 *
 * @method UI\Form getForm()
 * @property \Nette\Forms\Container $parent
 */
class Container extends \Nette\Forms\Container
{
    /** @var bool */
    public $forceDefault;

    /** @var int */
    public $createDefault;

    /** @var string */
    public $containerClass = '\Nette\Forms\Container';

    /** @var callable */
    protected $factoryCallback;

    /** @var boolean */
    private $submittedBy = false;

    /** @var array */
    private $created = [];

    /** @var Request */
    private $httpRequest;

    /** @var array */
    private $httpPost;

    /** @var UI\Control */
    private $control;

    /** @var Presenter */
    private $presenter;

    /** @var string|bool */
    private static $registered = false;


    /**
     * @param callable $factory
     * @param int $createDefault
     * @param bool $forceDefault
     *
     * @throws InvalidArgumentException
     */
    public function __construct(callable $factory, int $createDefault = 0, bool $forceDefault = false)
    {
        parent::__construct();
        $this->monitor(Presenter::class, function (Presenter $presenter) {
            $this->attached($presenter);
            $this->presenter = $presenter;
        });
        $this->monitor(Form::class, function (Form $form) {
            $this->attached($form);
        });

        $this->monitor(UI\Control::class, function (UI\Control $control) {
            $this->control = $control;
        });

        try {
            $this->factoryCallback = Callback::closure($factory);
        } catch (InvalidArgumentException $e) {
            $type = is_object($factory) ? 'instanceof ' . get_class($factory) : gettype($factory);
            throw new InvalidArgumentException(
                'Replicator requires callable factory, ' . $type . ' given.', 0, $e
            );
        }

        $this->createDefault = $createDefault;
        $this->forceDefault = $forceDefault;
    }


    /**
     * @param \Nette\ComponentModel\IContainer $obj
     */
    protected function attached($obj)
    {
        if (
            !$obj instanceof Presenter
            &&
            $this->form instanceof UI\Form
        ) {
            return;
        }

        $this->loadHttpData();
        $this->createDefault();
    }

    /**
     * @param callable $factory
     */
    public function setFactory($factory)
    {
        $this->factoryCallback = Callback::closure($factory);
    }

    /**
     * @param boolean $recursive
     * @return \Iterator|\Nette\Forms\Container[]
     */
    public function getContainers($recursive = false)
    {
        return $this->getComponents($recursive, '\Nette\Forms\Container');
    }

    /**
     * @param boolean $recursive
     * @return \Iterator|SubmitButton[]
     */
    public function getButtons($recursive = false)
    {
        return $this->getComponents($recursive, '\Nette\Forms\ISubmitterControl');
    }

    /**
     * Magical component factory
     *
     * @param string $name
     * @return \Nette\Forms\Container
     */
    protected function createComponent($name)
    {
        $container = $this->createContainer();
        $container->currentGroup = $this->currentGroup;
        $this->addComponent($container, $name, $this->getFirstControlName());

        Callback::invokeSafe($this->factoryCallback, [$container], null);

        return $this->created[$container->name] = $container;
    }


    /**
     * @return string
     */
    private function getFirstControlName()
    {
        $controls = iterator_to_array($this->getComponents(false, '\Nette\Forms\IControl'));
        $firstControl = reset($controls);
        return $firstControl ? $firstControl->name : null;
    }


    /**
     * @return \Nette\Forms\Container
     */
    protected function createContainer()
    {
        $class = $this->containerClass;
        return new $class();
    }

    /**
     * @return boolean
     */
    public function isSubmittedBy()
    {
        if ($this->submittedBy) {
            return true;
        }

        foreach ($this->getButtons(true) as $button) {
            if ($button->isSubmittedBy()) {
                return $this->submittedBy = true;
            }
        }

        return false;
    }

    /**
     * Create new container
     *
     * @param string|int $name
     *
     * @throws InvalidArgumentException
     * @return \Nette\Forms\Container|IComponent
     */
    public function createOne($name = null)
    {
        if ($name === null) {
            $names = array_keys(iterator_to_array($this->getContainers()));
            $name = $names ? max($names) + 1 : 0;
        }

        // Container is overriden, therefore every request for getComponent($name, FALSE) would return container
        if (isset($this->created[$name])) {
            throw new InvalidArgumentException("Container with name '$name' already exists.");
        }

        return $this[$name];
    }

    /**
     * @param array|\Traversable $values
     * @param bool $erase
     * @return \Nette\Forms\Container|Container
     */
    public function setValues($values, $erase = false)
    {
        if (!$this->getForm()->isAnchored() || !$this->getForm()->isSubmitted()) {
            foreach ($values as $name => $value) {
                if ((is_array($value) || $value instanceof \Traversable) && !$this->getComponent($name, false)) {
                    $this->createOne($name);
                }
            }
        }

        return parent::setValues($values, $erase);
    }

    /**
     * Loads data received from POST
     * @internal
     */
    protected function loadHttpData()
    {
        if (!$this->getForm()->isSubmitted()) {
            return;
        }

        foreach ((array)$this->getHttpData() as $name => $value) {
            if ((is_array($value) || $value instanceof \Traversable) && !$this->getComponent($name, false)) {
                $this->createOne($name);
            }
        }
    }

    /**
     * Creates default containers
     * @internal
     */
    protected function createDefault()
    {
        if (!$this->createDefault) {
            return;
        }

        if (!$this->getForm()->isSubmitted()) {
            $i = 0;
            while (iterator_count($this->getContainers()) < $this->createDefault) {
                if (!isset($this->created[$i])) {
                    $this->createOne($i);
                }
                $i++;
            }

        } elseif ($this->forceDefault) {
            while (iterator_count($this->getContainers()) < $this->createDefault) {
                $this->createOne();
            }
        }
    }

    /**
     * @param string $name
     * @return array|null
     */
    protected function getContainerValues($name)
    {
        $post = $this->getHttpData();
        return isset($post[$name]) ? $post[$name] : null;
    }

    /**
     * @return mixed|null
     */
    private function getHttpData()
    {
        if ($this->httpPost === null) {
            $path = explode(self::NAME_SEPARATOR, (string)$this->lookupPath('\Nette\Forms\Form'));
            $this->httpPost = \Nette\Utils\Arrays::get($this->getForm()->getHttpData(), $path, null);
        }

        return $this->httpPost;
    }


    /**
     * @param mixed $container
     * @param boolean $cleanUpGroups
     * @return void
     */
    public function remove($container, $cleanUpGroups = false)
    {
        if ($container->getParent() !== $this) {
            throw new InvalidArgumentException('Given component ' . $container->getName() . ' is not a child of ' . $this->name . '.');
        }

        // to check if form was submitted by this one
        foreach ($container->getComponents(true, '\Nette\Forms\ISubmitterControl') as $button) {
            /** @var SubmitButton $button */
            if ($button->isSubmittedBy()) {
                $this->submittedBy = true;
                break;
            }
        }

        /** @var BaseControl[] $components */
        $components = $container->getComponents(true);
        $this->removeComponent($container);

        // reflection is required to hack form groups
        $groupRefl = \Nette\Reflection\ClassType::from('\Nette\Forms\ControlGroup');
        $controlsProperty = $groupRefl->getProperty('controls');
        $controlsProperty->setAccessible(true);

        // walk groups and clean then from removed components
        $affected = [];
        foreach ($this->getForm()->getGroups() as $group) {
            /** @var \SplObjectStorage $groupControls */
            $groupControls = $controlsProperty->getValue($group);

            foreach ($components as $control) {
                if ($groupControls->contains($control)) {
                    $groupControls->detach($control);

                    if (!in_array($group, $affected, true)) {
                        $affected[] = $group;
                    }
                }
            }
        }

        // remove affected & empty groups
        if ($cleanUpGroups && $affected) {
            foreach ($this->getForm()->getComponents(false, '\Nette\Forms\Container') as $container) {
                if ($index = array_search($container->currentGroup, $affected, true)) {
                    unset($affected[$index]);
                }
            }

            /** @var \Nette\Forms\ControlGroup[] $affected */
            foreach ($affected as $group) {
                if (!$group->getControls() && in_array($group, $this->getForm()->getGroups(), true)) {
                    $this->getForm()->removeGroup($group);
                }
            }
        }
    }

    /**
     * Counts filled values, filtered by given names
     *
     * @param array $components
     * @param array $subComponents
     * @return int
     */
    public function countFilledWithout(array $components = [], array $subComponents = [])
    {
        $httpData = array_diff_key((array)$this->getHttpData(), array_flip($components));

        if (!$httpData) {
            return 0;
        }

        $rows = [];
        $subComponents = array_flip($subComponents);
        foreach ($httpData as $item) {
            $filter = function ($value) use (&$filter) {
                if (is_array($value)) {
                    return count(array_filter($value, $filter)) > 0;
                }
                return strlen($value);
            };
            $rows[] = array_filter(array_diff_key($item, $subComponents), $filter) ?: false;
        }

        return count(array_filter($rows));
    }

    /**
     * @param array $exceptChildren
     * @return bool
     */
    public function isAllFilled(array $exceptChildren = [])
    {
        $components = [];
        foreach ($this->getComponents(false, '\Nette\Forms\IControl') as $control) {
            /** @var BaseControl $control */
            $components[] = $control->getName();
        }

        foreach ($this->getContainers() as $container) {
            foreach ($container->getComponents(true, '\Nette\Forms\ISubmitterControl') as $button) {
                /** @var SubmitButton $button */
                $exceptChildren[] = $button->getName();
            }
        }

        $filled = $this->countFilledWithout($components, array_unique($exceptChildren));
        return $filled === iterator_count($this->getContainers());
    }

    /**
     * @param string $name
     * @return \Nette\Forms\Container
     */
    public function addContainer($name)
    {
        return $this[$name] = new \Nette\Forms\Container();
    }

    /**
     * @param IComponent $component
     * @param string $name
     * @param string|null $insertBefore
     * @return Container
     */
    public function addComponent(IComponent $component, $name, $insertBefore = null)
    {
        $group = $this->currentGroup;
        $this->currentGroup = null;
        parent::addComponent($component, $name, $insertBefore);
        $this->currentGroup = $group;
        return $this;
    }

    /**
     * @param string $methodName
     * @param string $containerClass
     * @return void
     */
    public static function register(string $methodName = 'addDynamic', string $containerClass = '\Nette\Forms\Container')
    {
        if (self::$registered) {
            \Nette\Forms\Container::extensionMethod(self::$registered, function () {
                throw new \Nette\MemberAccessException;
            });
        }

        \Nette\Forms\Container::extensionMethod($methodName, function (\Nette\Forms\Container $_this, $name, $factory, $createDefault = 0, $forceDefault = false) use ($containerClass) {
            $control = new Container($factory, $createDefault, $forceDefault);
            $control->currentGroup = $_this->currentGroup;
            $control->containerClass = $containerClass;
            return $_this[$name] = $control;
        });

        if (self::$registered) {
            return;
        }

        SubmitButton::extensionMethod('addRemoveOnClick', function (SubmitButton $_this, $callback = null, array $snippets = []) {
            $_this->setValidationScope(false);
            $_this->onClick[] = function (SubmitButton $button) use ($callback, $snippets) {
                /** @var Container $replicator */
                $replicator = $button->lookup(__NAMESPACE__ . '\Container');

                /** @var UI\Control $control */
                $control = $button->lookup(UI\Control::class);

                if (is_callable($callback)) {
                    Callback::invokeSafe($callback, [$replicator, $button->parent], null);
                }
                if ($form = $button->getForm(false)) {
                    $form->onSuccess = [];
                }

                if ($button->parent) {
                    $replicator->remove($button->parent);
                }

                if ($control && $snippets) {
                    foreach ($snippets as $snippet) {
                        $control->redrawControl($snippet);
                    }
                }
            };
            return $_this;
        });

        SubmitButton::extensionMethod('addCreateOnClick', function (SubmitButton $_this, bool $allowEmpty = false, $callback = null, array $snippets = []) {
            $_this->setValidationScope(false);
            $_this->onClick[] = function (SubmitButton $button) use ($allowEmpty, $callback, $snippets) {
                /** @var Container $replicator */
                $replicator = $button->lookup(__NAMESPACE__ . '\Container');

                /** @var UI\Control $control */
                $control = $button->lookup(UI\Control::class);

                if (!is_bool($allowEmpty)) {
                    $callback = Callback::closure($allowEmpty);
                    $allowEmpty = false;
                }

                if ($allowEmpty === true || $replicator->isAllFilled() === true) {
                    $newContainer = $replicator->createOne();
                    if (is_callable($callback)) {
                        Callback::invokeSafe($callback, [$replicator, $newContainer], null);
                    }
                }

                if ($button->getForm() instanceof Form) {
                    $button->getForm()->onSuccess = [];
                }

                if ($control && $snippets) {
                    foreach ($snippets as $snippet) {
                        $control->redrawControl($snippet);
                    }
                }
            };
            return $_this;
        });

        self::$registered = $methodName;
    }

}
