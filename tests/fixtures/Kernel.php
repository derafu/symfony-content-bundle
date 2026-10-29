<?php

declare(strict_types=1);

namespace Derafu\TestsContentBundle\Fixture;

use Derafu\ContentBundle\ContentBundle;
use Derafu\TranslationBundle\TranslationBundle;
use Derafu\TwigComponentsBundle\TwigComponentsBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\UX\TwigComponent\TwigComponentBundle;

final class Kernel extends BaseKernel
{
    /** @param array<string, mixed> $options */
    public function __construct(
        private readonly string $project,
        private readonly array $options = [],
        bool $debug = true,
        private readonly bool $applicationRenderer = false,
    ) {
        parent::__construct('test', $debug);
    }

    /**
     * {@inheritDoc}
     *
     * @return iterable<BundleInterface>
     */
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new TwigComponentBundle();
        yield new TranslationBundle();
        yield new TwigComponentsBundle();
        yield new ContentBundle();
    }

    /**
     * {@inheritDoc}
     */
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'secret' => 'content-test',
                'test' => true,
                'router' => ['resource' => $this->project . '/routes.yaml', 'utf8' => true],
                'translator' => ['fallbacks' => ['en']],
                'default_locale' => 'en',
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => false],
            ]);
            $container->loadFromExtension('twig', ['default_path' => $this->project . '/templates', 'strict_variables' => true]);
            $container->loadFromExtension('derafu_content', $this->options);
            $services = new YamlFileLoader($container, new FileLocator(__DIR__ . '/config'));
            $services->load('test-services.yaml');
            if ($this->applicationRenderer) {
                $services->load('application-renderer-services.yaml');
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function getProjectDir(): string
    {
        return $this->project;
    }

    /**
     * {@inheritDoc}
     */
    public function getCacheDir(): string
    {
        return $this->project . '/var/cache';
    }

    /**
     * {@inheritDoc}
     */
    public function getLogDir(): string
    {
        return $this->project . '/var/log';
    }
}
