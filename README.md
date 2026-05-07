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

final readonly class UserRegistered
{
    public function __construct(
        public string $email,
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

## Hook classes must be final

All hook classes must be declared `final` (enums are also accepted, as they are implicitly final).

The dispatcher matches hooks by exact class name. If inheritance were allowed, a handler subscribed to a parent class
would silently not fire for subclass instances — which is confusing. Requiring `final` makes this contract explicit:
one class, one set of handlers, no surprises.

It also nudges toward treating hooks as simple sealed value objects, which is the right model for them.

## PSR-14

This library deliberately does not implement [psr/event-dispatcher](https://packagist.org/packages/psr/event-dispatcher). A few reasons:

- The PSR calls them *events*; we prefer *hooks* — a subtly different mental model that better reflects push-based
  notifications rather than something that "happened" in the domain.
- `StoppableEventInterface` conflates stopping propagation with the event itself, which we consider a design smell.
  Stopping propagation is a dispatcher concern, not a data concern.
- PSR-14 adoption in the ecosystem is limited, so the interoperability argument is weak in practice.

If you do need PSR-14 compatibility, writing a thin adapter is straightforward.

## License

[MIT](LICENSE)
