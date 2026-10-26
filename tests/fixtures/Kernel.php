<?php

declare(strict_types=1);

namespace Derafu\TestsContentBundle\Fixture;

use Derafu\Content\Contract\ContentServiceInterface;
use Derafu\ContentBundle\ContentBundle;
use Derafu\Routing\Contract\UrlGeneratorInterface;
use Derafu\TranslationBundle\TranslationBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\UX\TwigComponent\TwigComponentBundle;

final class Kernel extends BaseKernel
{
    /** @param array<string, mixed> $options */
    public function __construct(private readonly string $project, private readonly array $options = [], bool $debug = true)
    {
        parent::__construct('test', $debug);
    }

    /** @return iterable<BundleInterface> */
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new TwigComponentBundle();
        yield new TranslationBundle();
        yield new ContentBundle();
    }

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
            foreach (['twig', 'router', 'translator', ContentServiceInterface::class, UrlGeneratorInterface::class, 'derafu_content.renderer'] as $id) {
                $container->setAlias('test.' . $id, $id)->setPublic(true);
            }
            $container->register('app.renderer', \Derafu\Renderer\Renderer::class);
            $container->setAlias(\Derafu\Renderer\Contract\RendererInterface::class, 'app.renderer');
            $container->setAlias('test.app.renderer', 'app.renderer')->setPublic(true);
            $container->register('logger', \Psr\Log\NullLogger::class);
            $container->register(FixtureController::class)->setPublic(true);
        });
    }

    public function getProjectDir(): string
    {
        return $this->project;
    }

    public function getCacheDir(): string
    {
        return $this->project . '/var/cache';
    }

    public function getLogDir(): string
    {
        return $this->project . '/var/log';
    }
}
