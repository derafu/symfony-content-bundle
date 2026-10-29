<?php

declare(strict_types=1);

namespace Derafu\TestsContentBundle\Fixture;

use Derafu\Renderer\Engine\Html\TwigHtmlEngine;

/** Adds application-owned context through the shared Twig engine collection. */
final class ApplicationTwigEngine extends TwigHtmlEngine
{
    /**
     * {@inheritDoc}
     */
    public function render(string $template, array $data = [], array $options = []): string
    {
        $data['application_banner'] = 'Application rendering policy';

        return parent::render($template, $data, $options);
    }
}
