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
use Derafu\Renderer\Contract\RendererInterface;
use Symfony\Component\DependencyInjection\Argument\BoundArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/** Applies bindings after all bundle extensions have loaded. */
final class ContentPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Append package fallbacks after Symfony has registered application
        // paths, so applications can override any Content template.
        $loader = $container->getDefinition('twig.loader.native_filesystem');
        foreach (['derafu/content', 'derafu/twig-components'] as $package) {
            $loader->addMethodCall('addPath', [InstalledVersions::getInstallPath($package) . '/resources/templates']);
        }
        $loader->addMethodCall('addPath', [__DIR__ . '/../../templates']);
        $container->getDefinition('twig')->addMethodCall('addGlobal', ['derafu_content_layout', '%derafu_content.layout%']);
        foreach ($container->getDefinitions() as $id => $service) {
            if (str_starts_with($id, 'Derafu\\Content\\Plugin\\') && str_ends_with($id, 'Controller')) {
                $service->setBindings(array_replace($service->getBindings(), [RendererInterface::class => new BoundArgument(new Reference('derafu_content.renderer'), false)]));
            }
        }
    }
}
