<?php

declare(strict_types=1);

/**
 * Derafu: Symfony Content Bundle - Content publishing with Symfony.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\ContentBundle;

use Composer\InstalledVersions;
use Derafu\Array\ArrayReader;
use Derafu\ContentBundle\DependencyInjection\ContentPass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class ContentBundle extends AbstractBundle
{
    protected string $extensionAlias = 'derafu_content';

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new ContentPass());
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()->children()
            ->stringNode('layout')->defaultValue('base.html.twig')->cannotBeEmpty()->end()
            ->arrayNode('content')->normalizeKeys(false)->variablePrototype()->end()->end()
        ->end();
    }

    /** @param array<string, mixed> $config */
    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        foreach (['content', 'twig', 'renderer'] as $package) {
            $configurator->import(InstalledVersions::getInstallPath('derafu/' . $package) . '/resources/config/' . $package . '-services.yaml');
        }
        $reader = new ArrayReader($config);
        $defaults = ArrayReader::fromValue($container->getParameter('derafu.content.config'))->toArray();
        $configuration = array_replace($defaults, $reader->getArray('content') ?? []);
        $configurator->parameters()->set('derafu.content.config', $configuration);
        $configurator->parameters()->set('derafu_content.layout', $reader->requireString('layout'));
        $configurator->import('../resources/config/symfony-content-bundle-services.yaml');

    }
}
