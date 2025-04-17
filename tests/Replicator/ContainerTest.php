<?php

/**
 * Test: Kdyby\Replicator\Container.
 *
 * @testCase Kdyby\Replicator\ContainerTest
 * @author   Filip Procházka <filip@prochazka.su>
 * @package  Kdyby\Replicator
 */

namespace KdybyTests\Replicator;

use Kdyby\Replicator\Container;
use Nette;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ContainerTest extends TestCase
{
	public function testReplicating()
	{
		$replicator = new Container(function (Nette\Forms\Container $container) {
			$container->addText('name', 'Name');
		});

		Assert::type(Nette\Forms\Controls\TextInput::class, $replicator[0]['name']);
		Assert::type(Nette\Forms\Controls\TextInput::class, $replicator[2]['name']);
		Assert::type(Nette\Forms\Controls\TextInput::class, $replicator[1000]['name']);
	}

	public function testRenderingAttachAfterDefinition(): void
	{
		$form = new BaseForm();
		$users = $form->addDynamic('users', function (Nette\Forms\Container $user) {
			$user->addText('name');
		}, 1);
		$users->addSubmit('add');

		$this->connectForm($form);

		// container and submit button
		Assert::same(2, iterator_count($users->getComponents()));

		// simulate rendering additional key
		Assert::type(Nette\Forms\Controls\TextInput::class, $users[2]['name']);

		// 2 containers and submit button
		Assert::same(3, iterator_count($users->getComponents()));

		Assert::equal(Nette\Utils\ArrayHash::from([
			'users' => [
				0 => [
					'name' => '',
				],
				2 => [
					'name' => '',
				],
			],
		]), $form->getValues());
	}

	public function testRenderingAttachBeforeDefinition(): void
	{
		$form = new BaseForm();

		$this->connectForm($form);

		$users = $form->addDynamic('users', function (Nette\Forms\Container $user) {
			$user->addText('name');
		}, 1);
		$users->addSubmit('add');

		// container and submit button
		Assert::same(2, iterator_count($users->getComponents()));

		// simulate rendering additional key
		Assert::type(Nette\Forms\Controls\TextInput::class, $users[2]['name']);

		// 2 containers and submit button
		Assert::same(3, iterator_count($users->getComponents()));

		Assert::equal(Nette\Utils\ArrayHash::from([
			'users' => [
				'0' => [
					'name' => '',
				],
				'2' => [
					'name' => '',
				],
			],
		]), $form->getValues());
	}

	public function testSubmitAttachAfterDefinition(): void
	{
		$form = new BaseForm();
		$users = $form->addDynamic('users', function (Nette\Forms\Container $user) {
			$user->addText('name');
		}, 1);
		$users->addSubmit('add');

		$this->connectForm($form, [
			'users' => [
				0 => [
					'name' => 'David',
				],
				2 => [
					'name' => 'Holy',
				],
				3 => [
					'name' => 'Rimmer',
				],
			],
			'_do' => 'form-submit',
		]);

		// container and submit button
		Assert::same(4, iterator_count($users->getComponents()));

		Assert::equal(Nette\Utils\ArrayHash::from([
			'users' => [
				0 => [
					'name' => 'David',
				],
				2 => [
					'name' => 'Holy',
				],
				3 => [
					'name' => 'Rimmer',
				],
			],
		]), $form->getValues());
	}

	public function testSubmitAttachBeforeDefinition(): void
	{
		$form = new BaseForm();

		$this->connectForm($form, [
			'users' => [
				0 => [
					'name' => 'David',
				],
				2 => [
					'name' => 'Holy',
				],
				3 => [
					'name' => 'Rimmer',
				],
			],
			'_do' => 'form-submit',
		]);

		$users = $form->addDynamic('users', function (Nette\Forms\Container $user) {
			$user->addText('name');
		}, 1);
		$users->addSubmit('add');

		// container and submit button
		Assert::same(4, iterator_count($users->getComponents()));

		Assert::equal(Nette\Utils\ArrayHash::from([
			'users' => [
				0 => [
					'name' => 'David',
				],
				2 => [
					'name' => 'Holy',
				],
				3 => [
					'name' => 'Rimmer',
				],
			],
		]), $form->getValues());
	}

	public function testSubmitNestedReplicatorNotFilled(): void
	{
		$form = new BaseForm();
		$this->connectForm($form, [
			'users' => [
				0 => [
					'emails' => [
						0 => [
							'email' => '',
						],
					],
				],
			],
			'_do' => 'form-submit',
		]);
		$users = $form->addDynamic('users', function (Nette\Forms\Container $user) {
			$user->addDynamic('emails', function (Nette\Forms\Container $email) {
				$email->addText('email');
				$email->addText('note');
			});
		});
		$users->addSubmit('add')
			->addCreateOnClick();
		Assert::false($users->isAllFilled());
	}

	public function testSubmitNestedReplicatorFilled(): void
	{
		$form = new BaseForm();
		$this->connectForm($form, [
			'users' => [
				0 => [
					'emails' => [
						0 => [
							'email' => 'foo',
							'note' => 'aa',
						],
					],
				],
			],
			'_do' => 'form-submit',
		]);
		$users = $form->addDynamic('users', function (Nette\Forms\Container $user) {
			$user->addDynamic('emails', function (Nette\Forms\Container $email) {
				$email->addText('email');
				$email->addText('note');
			});
		});
		$users->addSubmit('add')
			->addCreateOnClick();
		Assert::true($users->isAllFilled());
	}

	public function testAddContainer(): void
	{
		$form = new BaseForm();
		$this->connectForm($form, [
			'users' => [
				0 => [
					'emails' => [
						0 => [
							'email' => 'foo',
						],
					],
				],
				2 => [
					'emails' => [
						0 => [
							'email' => 'bar',
						],
					],
				],
			],
			'_do' => 'form-submit',
		]);
		$users = $form->addDynamic('users', function (Nette\Forms\Container $user) {
			$user->addDynamic('emails', function (Nette\Forms\Container $email) {
				$email->addText('email');
			});
		});
		/** @var Nette\Forms\Controls\SubmitButton $submit */
		$submit = $users->addSubmit('add')
			->addCreateOnClick();

		Assert::same(2, iterator_count($users->getContainers()));

		$submit->click();

		Assert::same(3, iterator_count($users->getContainers()));
	}

	public function testRemoveContainer(): void
	{
		$form = new BaseForm();
		$this->connectForm($form, [
			'users' => [
				0 => [
					'emails' => [
						0 => [
							'email' => 'foo',
						],
					],
				],
				2 => [
					'emails' => [
						0 => [
							'email' => 'bar',
						],
					],
				],
			],
			'_do' => 'form-submit',
		]);
		$users = $form->addDynamic('users', function (Nette\Forms\Container $user) {
			$user->addDynamic('emails', function (Nette\Forms\Container $email) {
				$email->addText('email');
			});
			$user->addSubmit('remove')
				->addRemoveOnClick();
		});

		Assert::same(2, iterator_count($users->getContainers()));

		/** @var Container $section */
		$section = $users['2'];
		$users->remove($section);

		Assert::same(1, iterator_count($users->getContainers()));

		/** @var Nette\Forms\Controls\SubmitButton $sectionRemoveButton */
		$sectionRemoveButton = $users['0']['remove'];
		$sectionRemoveButton->click();

		Assert::same(0, iterator_count($users->getContainers()));
	}

	private function connectForm(Nette\Application\UI\Form $form, array $post = []): MockPresenter
	{
		$container = Helper::createContainer();

		/** @var MockPresenter $presenter */
		$presenter = $container->createInstance(MockPresenter::class, [
			'form' => $form,
		]);
		$container->callInjects($presenter);
		$presenter->run(new Nette\Application\Request('Mock', $post ? 'POST' : 'GET', [
			'action' => 'default',
		], $post));

		$presenter->getComponent('form'); // connect form

		return $presenter;
	}

	// TODO: add tests using standalone \Nette\Forms\Form and not the UI\Form.
	// https://github.com/Kdyby/Replicator/issues/40
	// The Replicator can't be used with standalone \Nette\Forms\Form (so without the UI\Form).
	// Problem is that attached is not triggered, so values from Request are not populated to the container.

}

class BaseForm extends Nette\Application\UI\Form
{
	public function addDynamic(string $name, callable $factory, int $createDefault = 0, bool $forceDefault = FALSE): Container
	{
		$control = new Container($factory, $createDefault, $forceDefault);
		$control->currentGroup = $this->currentGroup;

		return $this[$name] = $control;
	}
}

class MockPresenter extends Nette\Application\UI\Presenter
{
	public function __construct(
		private readonly Nette\Application\UI\Form $form
	) {
		parent::__construct();
	}

	/**
	 * @throws Nette\Application\AbortException
	 */
	protected function beforeRender(): void
	{
		$this->terminate();
	}

	protected function createComponentForm(): Nette\Application\UI\Form
	{
		return $this->form;
	}
}

(new ContainerTest())->run();
