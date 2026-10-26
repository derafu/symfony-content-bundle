<?php

declare(strict_types=1);

/**
 * Derafu: Symfony Content Bundle - Content publishing with Symfony.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\ContentBundle\Routing;

use Closure;
use Derafu\Routing\Collection;
use Derafu\Routing\Contract\RouteInterface;
use Derafu\Routing\Contract\RouteRegistrarInterface;
use Derafu\Routing\ValueObject\Route;
use Derafu\Translation\Exception\Core\TranslatableLogicException;

/** Collects plugin definitions during routing compilation, never during requests. */
final class RouteRegistrar implements RouteRegistrarInterface
{
    private ?Collection $collection = null;

    /** @return array<RouteInterface> */
    public function collect(callable $initialize): array
    {
        if ($this->collection !== null) {
            throw new TranslatableLogicException('Content route collection is already in progress.');
        }
        $this->collection = new Collection();
        try {
            $initialize();

            return $this->collection->all();
        } finally {
            $this->collection = null;
        }
    }

    public function addRoute(string $name, string $path, string|array|Closure $handler, array $defaults = [], array $methods = [], array $roles = []): static
    {
        // Plugin initialization is replayed when serving content. Only the route
        // loader publishes definitions; production changes require cache warmup.
        $this->collection?->add(new Route($name, $path, $handler, $defaults, $methods, $roles));

        return $this;
    }
}
