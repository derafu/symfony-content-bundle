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

use Composer\InstalledVersions;
use Derafu\Array\ArrayReader;
use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentService;
use Derafu\Content\Contract\ContentLoaderInterface;
use Derafu\Content\Contract\ContentPluginInterface;
use Derafu\Content\PluginLoader;
use Derafu\Routing\RouteCollectionFactory;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Config\Resource\FileExistenceResource;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Yaml\Yaml;

/** Loads the package catalogue and plugin routes without initializing the router. */
final class ContentRouteLoader extends Loader
{
    /** @param array<string, mixed> $configuration */
    public function __construct(
        private readonly ContentLoaderInterface $contentLoader,
        private readonly RouteRegistrar $registrar,
        private readonly RouteConverter $converter,
        private readonly array $configuration,
        private readonly string $projectDirectory,
    ) {
        parent::__construct();
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === 'derafu_content';
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        $package = InstalledVersions::getInstallPath('derafu/content');
        $file = $package . '/resources/config/content-routes.yaml';
        $routes = (new RouteCollectionFactory())->create(ArrayReader::fromValue(Yaml::parseFile($file))->toArray());
        $collection = new RouteCollection();
        $collection->addResource(new FileResource($file));
        foreach ($routes->all() as $route) {
            $collection->add($route->getName(), $this->converter->convert($route));
        }

        // Route definitions must be discovered from current content, not a
        // registry cache populated by an earlier request or deployment.
        $config = new ContentConfig(array_replace($this->configuration, ['cache' => ['enabled' => false]]));
        $context = new ContentContext($config, new NullAdapter());
        $content = new ContentService($context, new PluginLoader($this->contentLoader, $this->registrar));
        foreach ($this->registrar->collect($content->plugins(...)) as $route) {
            $collection->add($route->getName(), $this->converter->convert($route));
        }
        $paths = [$this->projectDirectory . '/resources/content', $package . '/src'];
        foreach ($content->plugins() as $plugin) {
            if ($plugin instanceof ContentPluginInterface) {
                foreach ($plugin->getPathsToWatch() as $path) {
                    $paths[] = str_starts_with($path, '/') ? $path : $this->projectDirectory . '/' . $path;
                }
            }
        }
        foreach (array_unique($paths) as $path) {
            $collection->addResource(is_dir($path) ? new DirectoryResource($path) : new FileExistenceResource($path));
        }

        return $collection;
    }
}
