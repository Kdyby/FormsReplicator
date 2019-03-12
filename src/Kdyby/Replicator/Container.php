<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Replicator;

use ArrayIterator;
use Nette\Application\Request;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\ComponentModel\IComponent;
use Nette\ComponentModel\IContainer;
use Nette\Forms\Container as NetteFormsContainer;
use Nette\Forms\ControlGroup;
use Nette\Forms\Controls\BaseControl;
use Nette\Forms\Controls\SubmitButton;
use Nette\Http\IRequest;
use Nette\InvalidArgumentException;
use Nette\MemberAccessException;
use Nette\Reflection\ClassType;
use Nette\Utils\Arrays;
use Nette\Utils\Callback;
use Nette\Utils\ObjectMixin;
use SplObjectStorage;
use Traversable;


/**
 * @author Filip Procházka <filip@prochazka.su>
 * @author Jan Tvrdík
 *
 * @method Form getForm()
 * @property NetteFormsContainer $parent
 */
class Container extends NetteFormsContainer
{

	/** @var bool */
	public $forceDefault;

	/** @var int */
	public $createDefault;

	/** @var string */
	public $containerClass = 'Nette\Forms\Container';

	/** @var callable */
	protected $factoryCallback;

	/** @var boolean */
	private $submittedBy = FALSE;

	/** @var array */
	private $created = [];

	/** @var IRequest */
	private $httpRequest;

	/** @var array */
	private $httpPost;



	/**
	 * @param callable $factory
	 * @param int $createDefault
	 * @param bool $forceDefault
	 *
	 * @throws InvalidArgumentException
	 */
	public function __construct($factory, $createDefault = 0, $forceDefault = FALSE)
	{
		parent::__construct();
		$this->monitor('Nette\Application\UI\Presenter');
		$this->monitor('Nette\Forms\Form');

		try {
			$this->factoryCallback = Callback::closure($factory);
		} catch (InvalidArgumentException $e) {
			$type = is_object($factory) ? 'instanceof ' . get_class($factory) : gettype($factory);
			throw new InvalidArgumentException(
				'Replicator requires callable factory, ' . $type . ' given.', 0, $e
			);
		}

		$this->createDefault = (int)$createDefault;
		$this->forceDefault = $forceDefault;
	}



	/**
	 * @param callable $factory
	 */
	public function setFactory($factory)
	{
		$this->factoryCallback = Callback::closure($factory);
	}



	/**
	 * Magical component factory
	 *
	 * @param IContainer
	 */
	protected function attached($obj)
	{
		parent::attached($obj);

		if (
			!$obj instanceof Presenter
			&&
			$this->form instanceof Form
		) {
			return;
		}

		$this->loadHttpData();
		$this->createDefault();
	}



	/**
	 * @param boolean $recursive
	 *
	 * @return ArrayIterator|NetteFormsContainer[]
	 */
	public function getContainers($recursive = FALSE)
	{
		return $this->getComponents($recursive, 'Nette\Forms\Container');
	}



	/**
	 * @param boolean $recursive
	 *
	 * @return ArrayIterator|SubmitButton[]
	 */
	public function getButtons($recursive = FALSE)
	{
		return $this->getComponents($recursive, 'Nette\Forms\ISubmitterControl');
	}



	/**
	 * Magical component factory
	 *
	 * @param string $name
	 *
	 * @return NetteFormsContainer
	 */
	protected function createComponent($name)
	{
		$container = $this->createContainer($name);
		$container->currentGroup = $this->currentGroup;
		$this->addComponent($container, $name, $this->getFirstControlName());

		Callback::invoke($this->factoryCallback, $container);

		return $this->created[$container->name] = $container;
	}



	/**
	 * @return string
	 */
	private function getFirstControlName()
	{
		$controls = iterator_to_array($this->getComponents(FALSE, 'Nette\Forms\IControl'));
		$firstControl = reset($controls);
		return $firstControl ? $firstControl->name : NULL;
	}



	/**
	 * @param string $name
	 *
	 * @return NetteFormsContainer
	 */
	protected function createContainer($name)
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
			return TRUE;
		}

		foreach ($this->getButtons(TRUE) as $button) {
			if ($button->isSubmittedBy()) {
				return $this->submittedBy = TRUE;
			}
		}

		return FALSE;
	}



	/**
	 * Create new container
	 *
	 * @param string|int $name
	 *
	 * @return NetteFormsContainer
	 *@throws InvalidArgumentException
	 */
	public function createOne($name = NULL)
	{
		if ($name === NULL) {
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
	 * @param array|Traversable $values
	 * @param bool              $erase
	 * @param bool              $onlyDisabled
	 *
	 * @return NetteFormsContainer|Container
	 */
	public function setValues($values, $erase = FALSE, $onlyDisabled = FALSE)
	{
		if (!$this->form->isAnchored() || !$this->form->isSubmitted()) {
			foreach ($values as $name => $value) {
				if ((is_array($value) || $value instanceof Traversable) && !$this->getComponent($name, FALSE)) {
					$this->createOne($name);
				}
			}
		}

		return parent::setValues($values, $erase, $onlyDisabled);
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

		foreach ((array) $this->getHttpData() as $name => $value) {
			if ((is_array($value) || $value instanceof Traversable) && !$this->getComponent($name, FALSE)) {
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
			foreach (range(0, $this->createDefault - 1) as $key) {
				$this->createOne($key);
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
		return isset($post[$name]) ? $post[$name] : NULL;
	}



	/**
	 * @return mixed|NULL
	 */
	private function getHttpData()
	{
		if ($this->httpPost === NULL) {
			$path = explode(self::NAME_SEPARATOR, $this->lookupPath('Nette\Forms\Form'));
			$this->httpPost = Arrays::get($this->getForm()->getHttpData(), $path, NULL);
		}

		return $this->httpPost;
	}



	/**
	 * @internal
	 * @param Request $request
	 * @return Container
	 */
	public function setRequest(Request $request)
	{
		$this->httpRequest = $request;
		return $this;
	}



	/**
	 * @return Request
	 */
	private function getRequest()
	{
		if ($this->httpRequest !== NULL) {
			return $this->httpRequest;
		}

		return $this->httpRequest = $this->getForm()->getPresenter()->getRequest();
	}



	/**
	 * @param NetteFormsContainer $container
	 * @param boolean             $cleanUpGroups
	 *
	 * @return void
	 * @throws InvalidArgumentException
	 */
	public function remove(NetteFormsContainer $container, $cleanUpGroups = FALSE)
	{
		if ($container->parent !== $this) {
			throw new InvalidArgumentException('Given component ' . $container->name . ' is not children of ' . $this->name . '.');
		}

		// to check if form was submitted by this one
		foreach ($container->getComponents(TRUE, 'Nette\Forms\ISubmitterControl') as $button) {
			/** @var SubmitButton $button */
			if ($button->isSubmittedBy()) {
				$this->submittedBy = TRUE;
				break;
			}
		}

		/** @var BaseControl[] $components */
		$components = $container->getComponents(TRUE);
		$this->removeComponent($container);

		// reflection is required to hack form groups
		$groupRefl = ClassType::from('Nette\Forms\ControlGroup');
		$controlsProperty = $groupRefl->getProperty('controls');
		$controlsProperty->setAccessible(TRUE);

		// walk groups and clean then from removed components
		$affected = [];
		foreach ($this->getForm()->getGroups() as $group) {
			/** @var SplObjectStorage $groupControls */
			$groupControls = $controlsProperty->getValue($group);

			foreach ($components as $control) {
				if ($groupControls->contains($control)) {
					$groupControls->detach($control);

					if (!in_array($group, $affected, TRUE)) {
						$affected[] = $group;
					}
				}
			}
		}

		// remove affected & empty groups
		if ($cleanUpGroups && $affected) {
			foreach ($this->getForm()->getComponents(FALSE, 'Nette\Forms\Container') as $container) {
				if ($index = array_search($container->currentGroup, $affected, TRUE)) {
					unset($affected[$index]);
				}
			}

			/** @var ControlGroup[] $affected */
			foreach ($affected as $group) {
				if (!$group->getControls() && in_array($group, $this->getForm()->getGroups(), TRUE)) {
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
			$rows[] = array_filter(array_diff_key($item, $subComponents), $filter) ?: FALSE;
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
		foreach ($this->getComponents(FALSE, 'Nette\Forms\IControl') as $control) {
			/** @var BaseControl $control */
			$components[] = $control->getName();
		}

		foreach ($this->getContainers() as $container) {
			foreach ($container->getComponents(TRUE, 'Nette\Forms\ISubmitterControl') as $button) {
				/** @var SubmitButton $button */
				$exceptChildren[] = $button->getName();
			}
		}

		$filled = $this->countFilledWithout($components, array_unique($exceptChildren));
		return $filled === iterator_count($this->getContainers());
	}



	/**
	 * @param $name
	 *
	 * @return NetteFormsContainer
	 */
	public function addContainer($name)
	{
		return $this[$name] = new NetteFormsContainer();
	}



	/**
	 * @param IComponent $component
	 * @param $name
	 * @param null $insertBefore
	 * @return self
	 */
	public function addComponent(IComponent $component, $name, $insertBefore = NULL)
	{
		$group = $this->currentGroup;
		$this->currentGroup = NULL;
		parent::addComponent($component, $name, $insertBefore);
		$this->currentGroup = $group;
		return $this;
	}



	/**
	 * @var bool
	 */
	private static $registered = FALSE;

	/**
	 * @param string $methodName
	 * @return void
	 */
	public static function register($methodName = 'addDynamic')
	{
		if (self::$registered) {
			ObjectMixin::setExtensionMethod('Nette\Forms\Container', self::$registered, function () {
				throw new MemberAccessException;
			});
		}

		ObjectMixin::setExtensionMethod('Nette\Forms\Container', $methodName, function (NetteFormsContainer $_this, $name, $factory, $createDefault = 0, $forceDefault = FALSE) {
			$control = new Container($factory, $createDefault, $forceDefault);
			$control->currentGroup = $_this->currentGroup;
			return $_this[$name] = $control;
		});

		if (self::$registered) {
			return;
		}

		ObjectMixin::setExtensionMethod('Nette\Forms\Controls\SubmitButton', 'addRemoveOnClick', function (SubmitButton $_this, $callback = NULL) {
			$_this->setValidationScope(FALSE);
			$_this->onClick[] = function (SubmitButton $button) use ($callback) {
				$replicator = $button->lookup(__NAMESPACE__ . '\Container');
				/** @var Container $replicator */
				if (is_callable($callback)) {
					Callback::invoke($callback, $replicator, $button->parent);
				}
				if ($form = $button->getForm(FALSE)) {
					$form->onSuccess = [];
				}
				$replicator->remove($button->parent);
			};
			return $_this;
		});

		ObjectMixin::setExtensionMethod('Nette\Forms\Controls\SubmitButton', 'addCreateOnClick', function (SubmitButton $_this, $allowEmpty = FALSE, $callback = NULL) {
			$_this->onClick[] = function (SubmitButton $button) use ($allowEmpty, $callback) {
				$replicator = $button->lookup(__NAMESPACE__ . '\Container');
				/** @var Container $replicator */
				if (!is_bool($allowEmpty)) {
					$callback = Callback::closure($allowEmpty);
					$allowEmpty = FALSE;
				}
				if ($allowEmpty === TRUE || $replicator->isAllFilled() === TRUE) {
					$newContainer = $replicator->createOne();
					if (is_callable($callback)) {
						Callback::invoke($callback, $replicator, $newContainer);
					}
				}
				$button->getForm()->onSuccess = [];
			};
			return $_this;
		});

		self::$registered = $methodName;
	}

}
