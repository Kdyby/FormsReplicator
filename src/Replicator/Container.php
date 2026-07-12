<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Replicator;

use Closure;
use Nette;
use ReflectionClass;
use WeakMap;

/**
 * @author Filip Procházka <filip@prochazka.su>
 * @author Jan Tvrdík
 *
 * @method Nette\Application\UI\Form getForm()
 * @property Nette\Forms\Container $parent
 *
 * @phpstan-consistent-constructor
 */
class Container extends Nette\Forms\Container
{
	/**
	 * @var bool
	 */
	public $forceDefault;

	/**
	 * @var int
	 */
	public $createDefault;

	/**
	 * @var class-string<Nette\Forms\Container>
	 */
	public $containerClass = Nette\Forms\Container::class;

	/**
	 * @var callable
	 */
	protected $factoryCallback;

	/**
	 * @var boolean
	 */
	private $submittedBy = FALSE;

	/**
	 * @var array<string, Nette\Forms\Container>
	 */
	private $created = [];

	/**
	 * @var ?array<string, array<string, mixed>>
	 */
	private $httpPost;

	/**
	 * @throws Nette\InvalidArgumentException
	 */
	public function __construct(callable $factory, int $createDefault = 0, bool $forceDefault = FALSE)
	{
		$this->monitor(Nette\Application\UI\Presenter::class, $this->attached(...));
		$this->monitor(Nette\Forms\Form::class, $this->attached(...));

		try {
			$this->factoryCallback = Closure::fromCallable($factory);
		} catch (Nette\InvalidArgumentException $e) {
			$type = is_object($factory) ? 'instanceof ' . $factory::class : gettype($factory);
			throw new Nette\InvalidArgumentException(
				'Replicator requires callable factory, ' . $type . ' given.',
				0,
				$e
			);
		}

		$this->createDefault = $createDefault;
		$this->forceDefault = $forceDefault;
	}

	public function setFactory(callable $factory): void
	{
		$this->factoryCallback = Closure::fromCallable($factory);
	}

	/**
	 * Magical component factory
	 */
	protected function attached(Nette\ComponentModel\IComponent $obj): void
	{
		if (
			!$obj instanceof Nette\Application\UI\Presenter
			&&
			$this->form instanceof Nette\Application\UI\Form
		) {
			return;
		}

		$this->loadHttpData();
		$this->createDefault();
	}

	/**
	 * @return array<Nette\Forms\Container>
	 */
	public function getContainers(bool $recursive = FALSE): array
	{
		return array_filter(
			$recursive ? $this->getComponentTree() : $this->getComponents(),
			fn ($component): bool => $component instanceof \Nette\Forms\Container,
		);
	}

	/**
	 * @return array<Nette\Forms\Controls\SubmitButton>
	 */
	public function getButtons(bool $recursive = FALSE): array
	{
		return array_filter(
			$recursive ? $this->getComponentTree() : $this->getComponents(),
			fn ($component): bool => $component instanceof Nette\Forms\Controls\SubmitButton,
		);
	}

	/**
	 * Magical component factory
	 *
	 * @return Nette\Forms\Container
	 */
	protected function createComponent(string $name): ?Nette\ComponentModel\IComponent
	{
		$container = $this->createContainer();
		$container->currentGroup = $this->currentGroup;
		$this->addComponent($container, $name, $this->getFirstControlName());

		($this->factoryCallback)($container);

		return $this->created[$container->getName()] = $container;
	}

	private function getFirstControlName(): ?string
	{
		$controls = array_filter(
			$this->getComponents(),
			fn ($component): bool => $component instanceof Nette\Forms\Control,
		);
		$firstControl = reset($controls);

		return $firstControl ? $firstControl->getName() : NULL;
	}

	protected function createContainer(): Nette\Forms\Container
	{
		$class = $this->containerClass;

		return new $class();
	}

	public function isSubmittedBy(): bool
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
	 * @throws Nette\InvalidArgumentException
	 */
	public function createOne(?string $name = NULL): Nette\Forms\Container
	{
		if ($name === NULL) {
			$names = array_keys(iterator_to_array($this->getContainers()));
			$name = $names ? max($names) + 1 : 0;
			$name = (string) $name;
		}

		// Container is overriden, therefore every request for getComponent($name, FALSE) would return container
		if (isset($this->created[$name])) {
			throw new Nette\InvalidArgumentException("Container with name '{$name}' already exists.");
		}

		return $this->getComponent($name);
	}

	/**
	 * @param iterable<string, iterable<string, mixed>> $values
	 */
	public function setValues(array|object $values, bool $erase = FALSE, bool $onlyDisabled = FALSE): static
	{
		if (!$this->form?->isAnchored() || !$this->form->isSubmitted()) {
			foreach ($values as $name => $value) {
				if ((is_iterable($value)) && !$this->getComponent($name, FALSE)) {
					$this->createOne($name);
				}
			}
		}

		return parent::setValues($values, $erase, $onlyDisabled);
	}

	/**
	 * @internal
	 */
	protected function loadHttpData(): void
	{
		if (!$this->getForm()->isSubmitted()) {
			return;
		}

		foreach ((array) $this->getHttpData() as $name => $value) {
			if ((is_iterable($value)) && !$this->getComponent($name, FALSE)) {
				$this->createOne($name);
			}
		}
	}

	/**
	 * @internal
	 */
	protected function createDefault(): void
	{
		if (!$this->createDefault) {
			return;
		}

		if (!$this->getForm()->isSubmitted()) {
			foreach (range(0, $this->createDefault - 1) as $key) {
				$this->createOne((string) $key);
			}

		} elseif ($this->forceDefault) {
			while (iterator_count($this->getContainers()) < $this->createDefault) {
				$this->createOne();
			}
		}
	}

	/**
	 * @return ?array<string, array<string, mixed>>
	 */
	private function getHttpData(): ?array
	{
		if ($this->httpPost === NULL) {
			$path = explode(self::NameSeparator, $this->lookupPath(Nette\Forms\Form::class));
			/** @var array<string, mixed> */ // See https://github.com/nette/forms/pull/333
			$httpData = $this->getForm()
				->getHttpData();
			/** @var ?array<string, array<string, mixed>> */
			$httpPost = Nette\Utils\Arrays::get($httpData, $path, NULL);
			$this->httpPost = $httpPost;
		}

		return $this->httpPost;
	}

	/**
	 * @throws Nette\InvalidArgumentException
	 */
	public function remove(Nette\ComponentModel\Container $container, bool $cleanUpGroups = FALSE): void
	{
		if ($container->getParent() !== $this) {
			throw new Nette\InvalidArgumentException('Given component ' . $container->getName() . ' is not children of ' . $this->getName() . '.');
		}

		// to check if form was submitted by this one
		$buttons = array_filter(
			$container->getComponentTree(),
			fn ($component): bool => $component instanceof Nette\Forms\SubmitterControl,
		);
		foreach ($buttons as $button) {
			/** @var Nette\Forms\Controls\SubmitButton $button */
			if ($button->isSubmittedBy()) {
				$this->submittedBy = TRUE;
				break;
			}
		}

		/** @var Nette\Forms\Controls\BaseControl[] $components */
		$components = $container->getComponentTree();
		$this->removeComponent($container);

		// reflection is required to hack form groups
		$groupRefl = new ReflectionClass(Nette\Forms\ControlGroup::class);
		$controlsProperty = $groupRefl->getProperty('controls');

		// walk groups and clean then from removed components
		$affected = [];
		foreach ($this->getForm()->getGroups() as $group) {
			/** @var WeakMap<Nette\Forms\Control, null> $groupControls */
			$groupControls = $controlsProperty->getValue($group);

			foreach ($components as $control) {
				if ($groupControls->offsetExists($control)) {
					unset($groupControls[$control]);

					if (!in_array($group, $affected, TRUE)) {
						$affected[] = $group;
					}
				}
			}
		}

		// remove affected & empty groups
		if ($cleanUpGroups && $affected) {
			$containers = array_filter(
				$this->getForm()
					->getComponents(),
				fn ($component): bool => $component instanceof Nette\Forms\Container,
			);
			foreach ($containers as $cont) {
				if ($index = array_search($cont->currentGroup, $affected, TRUE)) {
					unset($affected[$index]);
				}
			}

			/** @var Nette\Forms\ControlGroup[] $affected */
			foreach ($affected as $group) {
				if (!$group->getControls() && in_array($group, $this->getForm()->getGroups(), TRUE)) {
					$this->getForm()
						->removeGroup($group);
				}
			}
		}
	}

	/**
	 * Counts filled values, filtered by given names
	 *
	 * @param array<string> $components
	 * @param array<string> $subComponents
	 */
	public function countFilledWithout(array $components = [], array $subComponents = []): int
	{
		$httpData = array_diff_key((array) $this->getHttpData(), array_flip($components));

		if (!$httpData) {
			return 0;
		}

		$rows = [];
		$subComponents = array_flip($subComponents);
		foreach ($httpData as $item) {
			$filter = function ($value) use (&$filter): bool {
				if (is_array($value)) {
					return count(array_filter($value, $filter)) > 0;
				}

				if (is_string($value)) {
					return strlen($value) > 0;
				}

				return true;
			};
			$rows[] = array_filter(array_diff_key($item, $subComponents), $filter) ?: FALSE;
		}

		return count(array_filter($rows));
	}

	/**
	 * @param array<string> $exceptChildren
	 */
	public function isAllFilled(array $exceptChildren = []): bool
	{
		$components = [];
		$controls = array_filter(
			$this->getComponents(),
			fn ($component): bool => $component instanceof Nette\Forms\Control,
		);
		foreach ($controls as $control) {
			if (($name = $control->getName()) !== null) {
				$components[] = $name;
			}
		}

		foreach ($this->getContainers() as $container) {
			$buttons = array_filter(
				$container->getComponentTree(),
				fn ($component): bool => $component instanceof Nette\Forms\SubmitterControl,
			);
			foreach ($buttons as $button) {
				if (($name = $button->getName()) !== null) {
					$exceptChildren[] = $name;
				}
			}
		}

		$filled = $this->countFilledWithout($components, array_unique($exceptChildren));

		return $filled === iterator_count($this->getContainers());
	}

	public function addContainer(string|int $name): Nette\Forms\Container
	{
		return $this[(string) $name] = new Nette\Forms\Container();
	}

	public function addComponent(Nette\ComponentModel\IComponent $component, ?string $name, ?string $insertBefore = NULL): static
	{
		$group = $this->currentGroup;
		$this->currentGroup = NULL;
		parent::addComponent($component, $name, $insertBefore);
		$this->currentGroup = $group;

		return $this;
	}

	private static ?string $registered = null;

	public static function register(string $methodName = 'addDynamic'): void
	{
		if (self::$registered !== null) {
			Nette\Forms\Container::extensionMethod(self::$registered, function () {
				throw new Nette\MemberAccessException();
			});
		}

		Nette\Forms\Container::extensionMethod(
			$methodName,
			function (Nette\Forms\Container $_this, string $name, callable $factory, int $createDefault = 0, bool $forceDefault = FALSE) {
				$control = new static($factory, $createDefault, $forceDefault);
				$control->currentGroup = $_this->currentGroup;

				return $_this[$name] = $control;
			}
		);

		if (self::$registered !== null) {
			return;
		}

		Nette\Forms\Controls\SubmitButton::extensionMethod(
			'addRemoveOnClick',
			function (Nette\Forms\Controls\SubmitButton $_this, ?callable $callback = NULL) {
				$_this->setValidationScope([]);
				$_this->onClick[] = function (Nette\Forms\Controls\SubmitButton $button) use ($callback) {
					/** @var self $replicator */
					$replicator = $button->lookup(static::class);
					$container = $button->getParent();
					\assert($container instanceof Nette\ComponentModel\Container);
					if (is_callable($callback)) {
						$callback($replicator, $container);
					}
					if ($form = $button->getForm(FALSE)) {
						$form->onSuccess = [];
					}
					$replicator->remove($container);
				};

				return $_this;
			}
		);

		Nette\Forms\Controls\SubmitButton::extensionMethod(
			'addCreateOnClick',
			function (Nette\Forms\Controls\SubmitButton $_this, bool $allowEmpty = FALSE, ?callable $callback = NULL) {
				$_this->onClick[] = function (Nette\Forms\Controls\SubmitButton $button) use ($allowEmpty, $callback) {
					/** @var self $replicator */
					$replicator = $button->lookup(static::class);
					if ($allowEmpty || $replicator->isAllFilled() === TRUE) {
						$newContainer = $replicator->createOne();
						if ($callback !== NULL) {
							$callback($replicator, $newContainer);
						}
					}
					$button->getForm()
						->onSuccess = [];
				};

				return $_this;
			}
		);

		self::$registered = $methodName;
	}
}
