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

use Derafu\Routing\Contract\RouteInterface;
use Derafu\Routing\Exception\UrlGeneratorException;
use Derafu\Routing\RoutePatternParser;
use Symfony\Component\Routing\Route;

/** Converts the shared route syntax without duplicating its placeholder parser. */
final class RouteConverter
{
    public function convert(RouteInterface $source): Route
    {
        $parts = RoutePatternParser::parse($source->getPath());
        if ($parts === null) {
            throw UrlGeneratorException::forPatternFailure($source->getPath(), preg_last_error_msg());
        }
        $path = '';
        $defaults = $source->getDefaults();
        $requirements = [];
        foreach ($parts as $part) {
            if (isset($part['literal'])) {
                $path .= $part['literal'];
                continue;
            }
            $path .= '{' . $part['name'] . '}';
            $requirements[$part['name']] = $part['requirement'];
            if ($part['optional'] && !array_key_exists($part['name'], $defaults)) {
                $defaults[$part['name']] = null;
            }
        }
        $handler = $source->getHandler();
        $defaults['_controller'] = is_string($handler) ? str_replace('@', '::', $handler) : $handler;
        $defaults['_derafu_content'] = true;
        $defaults['_derafu_content_roles'] = $source->getRoles();

        return new Route($path, $defaults, $requirements, methods: $source->getMethods());
    }
}
