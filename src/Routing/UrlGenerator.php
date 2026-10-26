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

use Derafu\Routing\Contract\RequestContextInterface;
use Derafu\Routing\Contract\UrlGeneratorInterface;
use Derafu\Routing\Enum\UrlReferenceType;
use Derafu\Routing\Exception\UrlGeneratorException;
use Derafu\Routing\ValueObject\RequestContext;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface as SymfonyUrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext as SymfonyRequestContext;

/** Delegates generation to the application's router, including import prefixes. */
final class UrlGenerator implements UrlGeneratorInterface
{
    public function __construct(
        private readonly SymfonyUrlGeneratorInterface $generator,
        private readonly SymfonyRequestContext $context,
    ) {
    }

    public function generate(string $name, array $parameters = [], UrlReferenceType $referenceType = UrlReferenceType::ABSOLUTE_PATH): string
    {
        try {
            return $this->generator->generate($name, $parameters, $referenceType->value);
        } catch (RouteNotFoundException) {
            throw UrlGeneratorException::forRouteNotFound($name);
        }
    }

    public function getContext(): RequestContextInterface
    {
        return new RequestContext(
            $this->context->getBaseUrl(),
            $this->context->getMethod(),
            $this->context->getHost(),
            $this->context->getScheme(),
            $this->context->getHttpPort(),
            $this->context->getHttpsPort(),
            $this->context->getPathInfo(),
            $this->context->getQueryString(),
        );
    }

    public function setContext(RequestContextInterface $context): static
    {
        $this->context->setBaseUrl($context->getBaseUrl());
        $this->context->setMethod($context->getMethod());
        $this->context->setHost($context->getHost());
        $this->context->setScheme($context->getScheme());
        $this->context->setHttpPort($context->getHttpPort());
        $this->context->setHttpsPort($context->getHttpsPort());
        $this->context->setPathInfo($context->getPathInfo());
        $this->context->setQueryString($context->getQueryString());

        return $this;
    }
}
