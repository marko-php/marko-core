<?php

declare(strict_types=1);

namespace Marko\Core;

use Marko\Core\Container\ContainerInterface;
use Marko\Core\Contracts\ResettableInterface;

/**
 * Clears request-scoped state between requests served by one booted
 * application: the RoadRunner worker and the marko/testing HTTP test client
 * both call reset() before handling each request.
 *
 * Every already-resolved ResettableInterface instance is discovered
 * generically via ContainerInterface::resolvedInstances(), never by a
 * hardcoded per-package list. Only instances the container has already
 * built are touched, since resolvedInstances() never forces instantiation:
 * a service no request used is never constructed just to reset it.
 *
 * Reset order is fixed and deterministic — ascending by container binding
 * identifier — so it never depends on which services happened to be
 * resolved first, in case a resettable's reset() needs to run relative to
 * another's. A reset() that throws propagates: swallowing it could leak one
 * request's state (a session, an identity) into the next.
 */
readonly class RequestStateResetter
{
    public function __construct(
        private ContainerInterface $container,
    ) {}

    public function reset(): void
    {
        /** @var array<string, ResettableInterface> $resettables */
        $resettables = $this->container->resolvedInstances(ResettableInterface::class);
        ksort($resettables);

        foreach ($resettables as $resettable) {
            $resettable->reset();
        }
    }
}
