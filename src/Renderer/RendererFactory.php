<?php

declare(strict_types=1);

/**
 * Derafu: Symfony Content Bundle - Content publishing with Symfony.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\ContentBundle\Renderer;

use Derafu\Markdown\Service\MarkdownService;
use Derafu\Renderer\Contract\RendererInterface;
use Derafu\Renderer\Engine\Html\MarkdownHtmlEngine;
use Derafu\Renderer\Engine\Html\TwigHtmlEngine;
use Derafu\Renderer\Engine\Pdf\HtmlPdfEngine;
use Derafu\Renderer\Renderer;
use Derafu\Twig\Service\TwigService;
use Twig\Environment;

/** Shares Symfony's Twig environment rather than creating a second one. */
final class RendererFactory
{
    public function create(Environment $twig, string $publicDirectory): RendererInterface
    {
        $service = new TwigService($twig);

        return new Renderer([
            'twig' => new TwigHtmlEngine($service),
            'markdown' => new MarkdownHtmlEngine(new MarkdownService(), $service),
            'pdf' => new HtmlPdfEngine($service, ['local_assets_path' => $publicDirectory]),
        ]);
    }
}
