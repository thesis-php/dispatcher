# Thesis Dispatcher

A lightweight hook dispatcher for PHP. Handlers subscribe to hook classes and are called when those hooks are dispatched.

## Installation

```shell
composer require thesis/dispatcher
```

## Usage

### Subscribing and dispatching

```php
use Thesis\Dispatcher;

final class UserRegistered
{
    public function __construct(
        public readonly string $email,
    ) {}
}

$dispatcher = new Dispatcher();

$dispatcher->subscribe(UserRegistered::class, function (UserRegistered $hook): void {
    echo "Welcome, {$hook->email}!\n";
});

$dispatcher->dispatch(new UserRegistered('user@example.com'));
// Welcome, user@example.com!
```

### Unsubscribing

`subscribe()` returns an unsubscribe closure:

```php
$unsubscribe = $dispatcher->subscribe(UserRegistered::class, function (): void {
    // ...
});

$unsubscribe(); // removed, will not be called on next dispatch
```

### Unsubscribing from within a handler

The handler receives an unsubscribe closure as its second argument — useful for one-shot handlers:

```php
$dispatcher->subscribe(UserRegistered::class, function (UserRegistered $hook, \Closure $unsubscribe): void {
    sendWelcomeEmail($hook->email);
    $unsubscribe(); // run once, then remove self
});
```

### Multiple handlers

All handlers subscribed to the same hook are called in subscription order:

```php
$dispatcher->subscribe(UserRegistered::class, $sendWelcomeEmail);
$dispatcher->subscribe(UserRegistered::class, $createDefaultSettings);
$dispatcher->subscribe(UserRegistered::class, $notifyAdmins);

$dispatcher->dispatch(new UserRegistered('user@example.com'));
// all three handlers are called in order
```

### Subscribing and unsubscribing during dispatch

It is safe to subscribe or unsubscribe handlers while a hook is being dispatched.
Handlers added during dispatch are called in the same pass; unsubscribed ones are skipped:

```php
$dispatcher->subscribe(UserRegistered::class, function (UserRegistered $hook, \Closure $unsubscribe) use ($dispatcher): void {
    echo "first\n";
    $unsubscribe(); // removes itself — skipped on future dispatches, does not affect others

    $dispatcher->subscribe(UserRegistered::class, function (): void {
        echo "third\n"; // added mid-dispatch — still called in this same pass
    });
});

$dispatcher->subscribe(UserRegistered::class, function (): void {
    echo "second\n";
});

$dispatcher->dispatch(new UserRegistered('user@example.com'));
// first
// second
// third

$dispatcher->dispatch(new UserRegistered('user@example.com'));
// second
// third
```

## License

[MIT](LICENSE)
