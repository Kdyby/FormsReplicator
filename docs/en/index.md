Quickstart
==========

Nette forms container replicator aka `addDynamic`.



Installation
------------

The best way to install Kdyby/Replicator is using  [Composer](http://getcomposer.org/):

```php
$ composer require kdyby/forms-replicator:@dev
```

Now you have to enable the extension using your neon config

```yml
extensions:
	replicator: Kdyby\Replicator\DI\ReplicatorExtension
```

Or place the Replicator class to folder, where RobotLoader can find it and add following line to `app/boostrap.php` or to `BasePresenter::startup()`.

```php
Kdyby\Replicator\Container::register();
```


Attaching to form
-----------------

It can be used for simple things, for example list of dates

```php
use Nette\Forms\Container;

$form->addDynamic('dates', function (Container $container) {
		$container->addDate('date');
});
```

Or complex combinations, for example users and their addresses

```php
$form->addDynamic('users', function (Container $user) {
		$user->addText('name', 'Name');
		$user->addText('surname', 'surbame');
		$user->addDynamic('addresses', function (Container $address) {
				$address->addText('street', 'Street');
				$address->addText('city', 'City');
				$address->addText('zip', 'Zip');
				// ...
		}, 1);
		// ...
}, 2);
```

There has been little misunderstanding, that when form is submitted, and new container is created, that replicator automatically adds default containers. I was not sure if this is the correct behaviour so I've added new options `$forceDefault` in [a934a07](https://github.com/Kdyby/Replicator/blob/master/src/Kdyby/Replicator/Container.php#L62) that won't let you have less than default count of containers in replicator.


Handling
--------

Handling is trivial, you just walk the values from user in cycle.

```php
use Nette\Application\UI\Form;

public function FormSubmitted(Form $form)
{
	foreach ($form['users']->values as $user) { // values from replicator
		dump($user['name'] . ' ' . $user['surname']);

		foreach ($user['addresses'] as $address) { // working with values from container
			dump($address['city']);
		}
	}
}
```

[WARNING]
Replicator is not suitable for handling file uploads. If you do not have detailed knowledge, how the forms work, and don't need Replicator's functionality specifically, consider using a [Multiple File Upload](http://addons.nette.org/jkuchar/multiplefileupload) component instead.


Editation of items
------------------

You can use names of nested containers as identifiers. From the nature of form containers, you can work with them like this:

```php
public function actionEditUsers()
{
	$form = $this['myForm'];
	if (!$form->isSubmitted()) { // if form was not submitted
		// expects instance of model class in presenter
		$users = $this->model->findAll();
		foreach ($users as $user) {
			$form['users'][$user->id]->setDefaults($user);
			// fill the container with default values
		}
	}
}
```

And modify the handling

```php
public function FormSubmitted(Form $form)
{
	foreach ($form['users']->values as $userId => $user) {
		// now we have asscesible ID of the user and associated values from the container
	}
}
```


Adding and removing of containers
---------------------------------

There is an example in sandbox, where every container has button to be deleted and at the end is button for adding new one

```php
protected function createComponentMyForm()
{
	$form = new Nette\Application\UI\Form;

	$removeEvent = callback($this, 'MyFormRemoveElementClicked');

	// name, factory, default count
	$users = $form->addDynamic('users', function (Container $user) use ($removeEvent) {
		// ...
		$user->addSubmit('remove', 'Remove')
			->setValidationScope(FALSE) # disables validation
			->onClick[] = $removeEvent;
	}, 1);

	$users->addSubmit('add', 'Add next person')
		->setValidationScope(FALSE)
		->onClick[] = callback($this, 'MyFormAddElementClicked');

	// ...
}
```

Handling of add button is easy. Next example is useful, when you expect that your users like to prepare more containers before they fill and submit them.

```php
use Nette\Forms\Controls\SubmitButton;

public function MyFormAddElementClicked(SubmitButton $button)
{
	$button->parent->createOne();
}
```

When you want to allow adding only one container each time, so there will be no more than one unfilled at time, you would have to check for values manualy, or with helper function.

```php
public function MyFormAddElementClicked(SubmitButton $button)
{
	$users = $button->parent;

	// count how many containers were filled
	if ($users->isAllFilled()) {
		// add one container to replicator
		$button->parent->createOne();
	}
}
```

Method `Replicator::isAllFilled()` checks, if the form controls are not empty. It's argument says which ones not to check.

When the user clicks to delete, the following event will be invoked

```php
public function MyFormRemoveElementClicked(SubmitButton $button)
{
	// first parent is container
	// second parent is it's replicator
	$users = $button->parent->parent;
	$users->remove($button->parent, TRUE);
}
```

If I'd want to for example delete user also from database and I have container names as identifiers, then I can read the value like this:

```php
public function MyFormRemoveElementClicked(SubmitButton $button)
{
	$id = $button->parent->name;
}
```


Manual rendering
----------------

When you add a submit button to replicator, you certainly don't want to try it render as container, so for skipping them, there is a method `getContainers()`, that will return only existing [containers](doc:/en/forms#toc-addcontainer).

```html
{form myForm}
{foreach $form['users']->containers as $user}

	{$user['name']->control} {$user['name']->label}

{/foreach}
{/form}
```

Or with form macros

```html
{form myForm}
{foreach $form['users']->containers as $id => $user}

	{input users-$id-name} {label users-$id-name /}

{/foreach}
{/form}
```
