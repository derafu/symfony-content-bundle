<?php

declare(strict_types=1);

/**
 * Derafu: Symfony Content Bundle - Content publishing with Symfony.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\ContentBundle\DependencyInjection;

use Composer\InstalledVersions;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/** Appends Content template fallbacks and its application-layout variable. */
final class ContentPass implements CompilerPassInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container): void
    {
        // Append package fallbacks after Symfony has registered application
        // paths, so applications can override any Content template.
        $loader = $container->getDefinition('twig.loader.native_filesystem');
        $loader->addMethodCall('addPath', [InstalledVersions::getInstallPath('derafu/content') . '/resources/templates']);
        $loader->addMethodCall('addPath', [__DIR__ . '/../../templates']);
        $container->getDefinition('twig')->addMethodCall('addGlobal', ['derafu_content_layout', '%derafu.content_bundle.layout%']);
    }
}
