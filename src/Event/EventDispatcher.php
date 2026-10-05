<?php

declare(strict_types=1);

namespace Marko\Core\Event;

use Marko\Core\Container\ContainerInterface;
use Marko\Core\Exceptions\EventException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class EventDispatcher implements EventDispatcherInterface
{
    /**
     * Resolved on the first async observer, never before, so requests that
     * dispatch no async observers never build the queue or open its connection.
     */
    private ?AsyncObserverDispatcherInterface $asyncObserverDispatcher = null;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ObserverRegistry $registry,
    ) {}

    /**
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface|EventException
     */
    public function dispatch(
        Event $event,
    ): void {
        $eventClass = $event::class;
        $observers = $this->registry->getObserversFor($eventClass);

        // Sort by priority (higher first)
        usort($observers, fn (ObserverDefinition $a, ObserverDefinition $b) => $b->priority <=> $a->priority);

        foreach ($observers as $definition) {
            if ($event->propagationStopped) {
                break;
            }

            if ($definition->async) {
                $this->asyncObserverDispatcher($definition->observerClass, $eventClass)
                    ->dispatch($definition->observerClass, $event);

                continue;
            }

            $observer = $this->container->get($definition->observerClass);
            $observer->handle($event);
        }
    }

    /**
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface|EventException
     */
    private function asyncObserverDispatcher(
        string $observerClass,
        string $eventClass,
    ): AsyncObserverDispatcherInterface {
        if ($this->asyncObserverDispatcher !== null) {
            return $this->asyncObserverDispatcher;
        }

        if (!$this->container->has(AsyncObserverDispatcherInterface::class)) {
            throw EventException::noAsyncObserverDispatcher($observerClass, $eventClass);
        }

        return $this->asyncObserverDispatcher = $this->container->get(AsyncObserverDispatcherInterface::class);
    }
}
