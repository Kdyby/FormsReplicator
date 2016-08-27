<?php

/**
 * Test: Kdyby\Replicator\Container.
 *
 * @testCase Kdyby\Replicator\ContainerTest
 * @author Filip Procházka <filip@prochazka.su>
 * @package Kdyby\Replicator
 */

namespace KdybyTests\Replicator;

use Kdyby;
use Kdyby\Replicator\Container;
use Nette;
use Nette\Application\Request;
use Nette\Application\UI;
use Nette\Forms\Controls;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ContainerTest extends Tester\TestCase
{

	public function testReplicating()
	{
		$replicator = new Container(function (Nette\Forms\Container $container) {
			$container->addText('name', "Name");
		});

		Assert::true($replicator[0]['name'] instanceof Controls\TextInput);
		Assert::true($replicator[2]['name'] instanceof Controls\TextInput);
		Assert::true($replicator[1000]['name'] instanceof Controls\TextInput);
	}



	public function testRendering_attachAfterDefinition()
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
		Assert::true($users[2]['name'] instanceof Controls\TextInput);

		// 2 containers and submit button
		Assert::same(3, iterator_count($users->getComponents()));

		Assert::same(['users' => [
			0 => ['name' => ''],
			2 => ['name' => ''],
		]], $form->getValues(TRUE));
	}



	public function testRendering_attachBeforeDefinition()
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
		Assert::true($users[2]['name'] instanceof Controls\TextInput);

		// 2 containers and submit button
		Assert::same(3, iterator_count($users->getComponents()));

		Assert::same(['users' => [
			0 => ['name' => ''],
			2 => ['name' => ''],
		]], $form->getValues(TRUE));
	}



	public function testSubmit_attachAfterDefinition()
	{
		$form = new BaseForm();
		$users = $form->addDynamic('users', function (Nette\Forms\Container $user) {
			$user->addText('name');
		}, 1);
		$users->addSubmit('add');

		$this->connectForm($form, [
			'users' => [
				0 => ['name' => 'David'],
				2 => ['name' => 'Holy'],
				3 => ['name' => 'Rimmer'],
			],
			'do' => 'form-submit'
		]);

		// container and submit button
		Assert::same(4, iterator_count($users->getComponents()));

		Assert::same(['users' => [
			0 => ['name' => 'David'],
			2 => ['name' => 'Holy'],
			3 => ['name' => 'Rimmer'],
		]], $form->getValues(TRUE));
	}



	public function testSubmit_attachBeforeDefinition()
	{
		$form = new BaseForm();

		$this->connectForm($form, [
			'users' => [
				0 => ['name' => 'David'],
				2 => ['name' => 'Holy'],
				3 => ['name' => 'Rimmer'],
			],
			'do' => 'form-submit'
		]);

		$users = $form->addDynamic('users', function (Nette\Forms\Container $user) {
			$user->addText('name');
		}, 1);
		$users->addSubmit('add');

		// container and submit button
		Assert::same(4, iterator_count($users->getComponents()));

		Assert::same(['users' => [
			0 => ['name' => 'David'],
			2 => ['name' => 'Holy'],
			3 => ['name' => 'Rimmer'],
		]], $form->getValues(TRUE));
	}



	public function testSubmit_nestedReplicator_notFilled()
	{
		$form = new BaseForm();
		$this->connectForm($form, [
			'users' => [0 => ['emails' => [0 => ['email' => '']]]],
			'do' => 'form-submit',
		]);
		$users = $form->addDynamic('users', function (Nette\Forms\Container $user) {
			$user->addDynamic('emails', function (Nette\Forms\Container $email) {
				$email->addText('email');
				$email->addText('note');
			});
		});
		$users->addSubmit('add')->addCreateOnClick();
		Assert::false($users->isAllFilled());
	}



	public function testSubmit_nestedReplicator_filled()
	{
		$form = new BaseForm();
		$this->connectForm($form, [
			'users' => [0 => ['emails' => [0 => ['email' => 'foo']]]],
		    'do' => 'form-submit',
		]);
		$users = $form->addDynamic('users', function (Nette\Forms\Container $user) {
			$user->addDynamic('emails', function (Nette\Forms\Container $email) {
				$email->addText('email');
				$email->addText('note');
			});
		});
		$users->addSubmit('add')->addCreateOnClick();
		Assert::true($users->isAllFilled());
	}



	protected function connectForm(UI\Form $form, array $post = [])
	{
		$container = $this->createContainer();

		/** @var MockPresenter $presenter */
		$presenter = $container->createInstance('KdybyTests\Replicator\MockPresenter', ['form' => $form]);
		$container->callInjects($presenter);
		$presenter->run(new Request('Mock', $post ? 'POST' : 'GET', ['action' => 'default'], $post));

		$presenter['form']; // connect form

		return $presenter;
	}



	/**
	 * @return \SystemContainer|\Nette\DI\Container
	 */
	protected function createContainer()
	{
		$config = new Nette\Configurator();
		$config->setTempDirectory(TEMP_DIR);
		Kdyby\Replicator\DI\ReplicatorExtension::register($config);

		return $config->createContainer();
	}

}



class BaseForm extends UI\Form
{

	/**
	 * @param string $name
	 * @param callable $factory
	 * @param int $createDefault
	 * @param bool $forceDefault
	 * @return Container
	 */
	public function addDynamic($name, $factory, $createDefault = 0, $forceDefault = FALSE)
	{
		$control = new Container($factory, $createDefault, $forceDefault);
		$control->currentGroup = $this->currentGroup;
		return $this[$name] = $control;
	}

}

class MockPresenter extends Nette\Application\UI\Presenter
{

	/**
	 * @var UI\Form
	 */
	private $form;

	public function __construct(UI\Form $form)
	{
		$this->form = $form;
	}

	protected function beforeRender()
	{
		$this->terminate();
	}

	protected function createComponentForm()
	{
		return $this->form;
	}

}


\run(new ContainerTest());
