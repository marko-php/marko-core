<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Event\AsyncObserverDispatcherInterface;
use Marko\Core\Event\Event;
use Marko\Core\Event\EventDispatcher;
use Marko\Core\Event\ObserverDefinition;
use Marko\Core\Event\ObserverRegistry;
use Marko\Core\Exceptions\EventException;

// Test fixtures
class DispatcherTestEvent extends Event
{
    public array $handledBy = [];
}

class FirstObserver
{
    public function handle(
        DispatcherTestEvent $event,
    ): void {
        $event->handledBy[] = 'first';
    }
}

class SecondObserver
{
    public function handle(
        DispatcherTestEvent $event,
    ): void {
        $event->handledBy[] = 'second';
    }
}

class LowPriorityObserver
{
    public function handle(
        DispatcherTestEvent $event,
    ): void {
        $event->handledBy[] = 'low';
    }
}

class HighPriorityObserver
{
    public function handle(
        DispatcherTestEvent $event,
    ): void {
        $event->handledBy[] = 'high';
    }
}

class MediumPriorityObserver
{
    public function handle(
        DispatcherTestEvent $event,
    ): void {
        $event->handledBy[] = 'medium';
    }
}

// Dependency for testing DI
class LoggerDependency
{
    public function log(
        string $message,
    ): string {
        return "logged: $message";
    }
}

readonly class ObserverWithDependency
{
    public function __construct(
        private LoggerDependency $logger,
    ) {}

    public function handle(
        DispatcherTestEvent $event,
    ): void {
        $event->handledBy[] = $this->logger->log('handled');
    }
}

class StoppingObserver
{
    public function handle(
        DispatcherTestEvent $event,
    ): void {
        $event->handledBy[] = 'stopping';
        $event->stopPropagation();
    }
}

class AfterStopObserver
{
    public function handle(
        DispatcherTestEvent $event,
    ): void {
        $event->handledBy[] = 'after-stop';
    }
}

it('dispatches event to all registered observers', function (): void {
    $container = new Container();
    $registry = new ObserverRegistry();

    $registry->register(new ObserverDefinition(
        observerClass: FirstObserver::class,
        eventClass: DispatcherTestEvent::class,
    ));
    $registry->register(new ObserverDefinition(
        observerClass: SecondObserver::class,
        eventClass: DispatcherTestEvent::class,
    ));

    $dispatcher = new EventDispatcher($container, $registry);
    $event = new DispatcherTestEvent();

    $dispatcher->dispatch($event);

    expect($event->handledBy)->toContain('first')
        ->and($event->handledBy)->toContain('second')
        ->and($event->handledBy)->toHaveCount(2);
});

it('executes observers in priority order (higher first)', function (): void {
    $container = new Container();
    $registry = new ObserverRegistry();

    // Register in non-priority order to verify sorting
    $registry->register(new ObserverDefinition(
        observerClass: LowPriorityObserver::class,
        eventClass: DispatcherTestEvent::class,
        priority: 10,
    ));
    $registry->register(new ObserverDefinition(
        observerClass: HighPriorityObserver::class,
        eventClass: DispatcherTestEvent::class,
        priority: 100,
    ));
    $registry->register(new ObserverDefinition(
        observerClass: MediumPriorityObserver::class,
        eventClass: DispatcherTestEvent::class,
        priority: 50,
    ));

    $dispatcher = new EventDispatcher($container, $registry);
    $event = new DispatcherTestEvent();

    $dispatcher->dispatch($event);

    // Higher priority should execute first
    expect($event->handledBy)->toBe(['high', 'medium', 'low']);
});

it('passes event object to observer handle method', function (): void {
    $container = new Container();
    $registry = new ObserverRegistry();

    // Observer that captures the event
    $observerClass = new class ()
    {
        public static ?Event $capturedEvent = null;

        public function handle(
            DispatcherTestEvent $event,
        ): void {
            self::$capturedEvent = $event;
        }
    };

    $registry->register(new ObserverDefinition(
        observerClass: $observerClass::class,
        eventClass: DispatcherTestEvent::class,
    ));

    $dispatcher = new EventDispatcher($container, $registry);
    $event = new DispatcherTestEvent();

    $dispatcher->dispatch($event);

    expect($observerClass::$capturedEvent)->toBe($event);
});

it('injects observer dependencies via container', function (): void {
    $container = new Container();
    $registry = new ObserverRegistry();

    $registry->register(new ObserverDefinition(
        observerClass: ObserverWithDependency::class,
        eventClass: DispatcherTestEvent::class,
    ));

    $dispatcher = new EventDispatcher($container, $registry);
    $event = new DispatcherTestEvent();

    $dispatcher->dispatch($event);

    // The dependency was injected and used
    expect($event->handledBy)->toBe(['logged: handled']);
});

it('supports stopping event propagation from observer', function (): void {
    $container = new Container();
    $registry = new ObserverRegistry();

    // StoppingObserver has higher priority, runs first, stops propagation
    $registry->register(new ObserverDefinition(
        observerClass: StoppingObserver::class,
        eventClass: DispatcherTestEvent::class,
        priority: 100,
    ));
    $registry->register(new ObserverDefinition(
        observerClass: AfterStopObserver::class,
        eventClass: DispatcherTestEvent::class,
        priority: 50,
    ));

    $dispatcher = new EventDispatcher($container, $registry);
    $event = new DispatcherTestEvent();

    $dispatcher->dispatch($event);

    // Only the stopping observer ran, not the one after
    expect($event->handledBy)->toBe(['stopping'])
        ->and($event->propagationStopped)->toBeTrue();
});

class AsyncTestObserver
{
    /** @noinspection PhpUnused - Invoked via reflection */
    public function handle(
        DispatcherTestEvent $event,
    ): void {
        $event->handledBy[] = 'async';
    }
}

class RecordingAsyncObserverDispatcher implements AsyncObserverDispatcherInterface
{
    /** @var list<array{observerClass: string, event: Event}> */
    public array $dispatched = [];

    public function dispatch(
        string $observerClass,
        Event $event,
    ): void {
        $this->dispatched[] = ['observerClass' => $observerClass, 'event' => $event];
    }
}

/**
 * @return array{container: Container, asyncDispatcher: RecordingAsyncObserverDispatcher, resolutions: object}
 */
function createContainerWithAsyncDispatcher(): array
{
    $container = new Container();
    $asyncDispatcher = new RecordingAsyncObserverDispatcher();
    $resolutions = (object) ['count' => 0];

    $container->bind(
        AsyncObserverDispatcherInterface::class,
        function () use ($asyncDispatcher, $resolutions): AsyncObserverDispatcherInterface {
            $resolutions->count++;

            return $asyncDispatcher;
        },
    );

    return ['container' => $container, 'asyncDispatcher' => $asyncDispatcher, 'resolutions' => $resolutions];
}

function registerAsyncTestObserver(
    ObserverRegistry $registry,
    int $priority = 0,
): void {
    $registry->register(new ObserverDefinition(
        observerClass: AsyncTestObserver::class,
        eventClass: DispatcherTestEvent::class,
        priority: $priority,
        async: true,
    ));
}

it('hands async observers to the bound async observer dispatcher instead of running them inline', function (): void {
    ['container' => $container, 'asyncDispatcher' => $asyncDispatcher] = createContainerWithAsyncDispatcher();
    $registry = new ObserverRegistry();
    registerAsyncTestObserver($registry);
    $event = new DispatcherTestEvent();

    new EventDispatcher($container, $registry)->dispatch($event);

    expect($event->handledBy)->toBeEmpty()
        ->and($asyncDispatcher->dispatched)->toHaveCount(1)
        ->and($asyncDispatcher->dispatched[0]['observerClass'])->toBe(AsyncTestObserver::class)
        ->and($asyncDispatcher->dispatched[0]['event'])->toBe($event);
});

it(
    'throws an EventException with an install suggestion when an async observer fires and no dispatcher is bound',
    function (): void {
        $registry = new ObserverRegistry();
        registerAsyncTestObserver($registry);
        $event = new DispatcherTestEvent();
        $dispatcher = new EventDispatcher(new Container(), $registry);
        $thrown = null;

        try {
            $dispatcher->dispatch($event);
        } catch (EventException $e) {
            $thrown = $e;
        }

        expect($thrown)->toBeInstanceOf(EventException::class)
            ->and($thrown?->getMessage())->toBe(
                'Observer ' . AsyncTestObserver::class . ' is marked async but no queue is installed',
            )
            ->and($thrown?->getSuggestion())->toContain('composer require marko/queue marko/queue-sync')
            ->and($thrown?->getSuggestion())->toContain('async: true')
            ->and($event->handledBy)->toBeEmpty();
    },
);

it('does not resolve the async observer dispatcher when no async observer is dispatched', function (): void {
    ['container' => $container, 'resolutions' => $resolutions] = createContainerWithAsyncDispatcher();
    $registry = new ObserverRegistry();
    $registry->register(new ObserverDefinition(
        observerClass: FirstObserver::class,
        eventClass: DispatcherTestEvent::class,
    ));
    $event = new DispatcherTestEvent();

    new EventDispatcher($container, $registry)->dispatch($event);

    expect($event->handledBy)->toBe(['first'])
        ->and($resolutions->count)->toBe(0);
});

it('resolves the async observer dispatcher once and reuses it across dispatches', function (): void {
    [
        'container' => $container,
        'asyncDispatcher' => $asyncDispatcher,
        'resolutions' => $resolutions,
    ] = createContainerWithAsyncDispatcher();
    $registry = new ObserverRegistry();
    registerAsyncTestObserver($registry);
    $dispatcher = new EventDispatcher($container, $registry);

    $dispatcher->dispatch(new DispatcherTestEvent());
    $dispatcher->dispatch(new DispatcherTestEvent());

    expect($resolutions->count)->toBe(1)
        ->and($asyncDispatcher->dispatched)->toHaveCount(2);
});

it('keeps priority order and propagation across a mix of sync and async observers', function (): void {
    ['container' => $container, 'asyncDispatcher' => $asyncDispatcher] = createContainerWithAsyncDispatcher();

    $mixedRegistry = new ObserverRegistry();
    $mixedRegistry->register(new ObserverDefinition(
        observerClass: LowPriorityObserver::class,
        eventClass: DispatcherTestEvent::class,
        priority: 10,
    ));
    registerAsyncTestObserver($mixedRegistry, priority: 50);
    $mixedRegistry->register(new ObserverDefinition(
        observerClass: HighPriorityObserver::class,
        eventClass: DispatcherTestEvent::class,
        priority: 100,
    ));
    $mixed = new DispatcherTestEvent();
    $handledWhenQueued = [];
    $recordingDispatcher = new class ($asyncDispatcher, $handledWhenQueued) implements AsyncObserverDispatcherInterface
    {
        public function __construct(
            private readonly RecordingAsyncObserverDispatcher $inner,
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
            private array &$handledWhenQueued,
        ) {}

        public function dispatch(
            string $observerClass,
            Event $event,
        ): void {
            /** @var DispatcherTestEvent $event */
            $this->handledWhenQueued = $event->handledBy;
            $this->inner->dispatch($observerClass, $event);
        }
    };
    $container->instance(AsyncObserverDispatcherInterface::class, $recordingDispatcher);
    new EventDispatcher($container, $mixedRegistry)->dispatch($mixed);

    $stoppingRegistry = new ObserverRegistry();
    $stoppingRegistry->register(new ObserverDefinition(
        observerClass: StoppingObserver::class,
        eventClass: DispatcherTestEvent::class,
        priority: 100,
    ));
    registerAsyncTestObserver($stoppingRegistry, priority: 50);
    $stopped = new DispatcherTestEvent();
    new EventDispatcher($container, $stoppingRegistry)->dispatch($stopped);

    expect($mixed->handledBy)->toBe(['high', 'low'])
        ->and($handledWhenQueued)->toBe(['high'])
        ->and($asyncDispatcher->dispatched)->toHaveCount(1)
        ->and($stopped->handledBy)->toBe(['stopping']);
});
