<?php

declare(strict_types=1);

/**
 * Derafu: Symfony Content Bundle - Content publishing with Symfony.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\ContentBundle\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** Leaves Symfony's native path, url and translation functions untouched. */
final class ContentExtension extends AbstractExtension
{
    public function __construct(private readonly UrlGeneratorInterface $generator, private readonly RequestStack $requests)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('is_active_path', $this->isActivePath(...))];
    }

    /** @param array<string, mixed> $parameters */
    public function isActivePath(string $name, array $parameters = [], bool $partial = true): bool
    {
        $request = $this->requests->getCurrentRequest();
        if ($request === null) {
            return false;
        }
        $current = rtrim($request->getBaseUrl() . $request->getPathInfo(), '/');
        $path = rtrim(explode('?', $this->generator->generate($name, $parameters), 2)[0], '/');
        if (!$partial) {
            return $current === $path;
        }
        $segments = explode('/', ltrim($path, '/'));

        return array_slice(explode('/', ltrim($current, '/')), 0, count($segments)) === $segments;
    }
}
