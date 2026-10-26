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

use Derafu\Http\Request as ContentRequest;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;

/** Preserves the PSR request and Derafu's existing format negotiation. */
final class RequestConverter
{
    public function convert(Request $request): ContentRequest
    {
        $cached = $request->attributes->get('_derafu_content_request');
        if ($cached instanceof ContentRequest) {
            return $cached;
        }
        $factory = new Psr17Factory();
        $psr = (new PsrHttpFactory($factory, $factory, $factory, $factory))->createRequest($request);
        $headers = [];
        foreach ($psr->getHeaders() as $name => $values) {
            $headers[$name] = array_values($values);
        }
        $content = (new ContentRequest(
            $psr->getMethod(),
            $psr->getUri(),
            $headers,
            $psr->getBody(),
            $psr->getProtocolVersion(),
            $psr->getServerParams(),
        ))->withCookieParams($psr->getCookieParams())
            ->withQueryParams($psr->getQueryParams())
            ->withParsedBody($psr->getParsedBody())
            ->withUploadedFiles($psr->getUploadedFiles());
        foreach ($psr->getAttributes() as $name => $value) {
            $content = $content->withAttribute($name, $value);
        }
        $request->attributes->set('_derafu_content_request', $content);

        return $content;
    }
}
