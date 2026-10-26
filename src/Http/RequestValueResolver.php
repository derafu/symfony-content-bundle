<?php

declare(strict_types=1);

/**
 * Derafu: Symfony Content Bundle - Content publishing with Symfony.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\ContentBundle\Http;

use Derafu\Http\Contract\RequestInterface;
use Derafu\Http\Request as ContentRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final class RequestValueResolver implements ValueResolverInterface
{
    public function __construct(private readonly RequestConverter $converter)
    {
    }

    /** @return iterable<ContentRequest> */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if ($request->attributes->get('_derafu_content') === true
            && in_array($argument->getType(), [ContentRequest::class, RequestInterface::class], true)) {
            yield $this->converter->convert($request);
        }
    }
}
