<?php

declare(strict_types=1);

namespace Derafu\TestsContentBundle;

use Derafu\ContentBundle\Routing\RouteConverter;
use Derafu\ContentBundle\Routing\RouteRegistrar;
use Derafu\ContentBundle\Routing\UrlGenerator;
use Derafu\Routing\Enum\UrlReferenceType;
use Derafu\Routing\Exception\UrlGeneratorException;
use Derafu\Routing\ValueObject\RequestContext;
use Derafu\Routing\ValueObject\Route;
use Derafu\Translation\Exception\Core\TranslatableLogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGenerator as SymfonyGenerator;
use Symfony\Component\Routing\RequestContext as SymfonyContext;
use Symfony\Component\Routing\Route as SymfonyRoute;
use Symfony\Component\Routing\RouteCollection;

#[CoversClass(RouteConverter::class)]
#[CoversClass(RouteRegistrar::class)]
#[CoversClass(UrlGenerator::class)]
final class RoutingTest extends TestCase
{
    public function testOptionalParametersQuantifiersAndMetadataSurviveConversion(): void
    {
        $source = new Route('invoice', '/invoice/{id:\d{2,4}}/{format?}', 'InvoiceController@show', ['language' => 'en'], ['GET'], ['ROLE_READER']);
        $route = (new RouteConverter())->convert($source);
        self::assertSame('/invoice/{id}/{format}', $route->getPath());
        self::assertSame('\d{2,4}', $route->getRequirement('id'));
        self::assertTrue($route->hasDefault('format'));
        self::assertNull($route->getDefault('format'));
        self::assertSame('InvoiceController::show', $route->getDefault('_controller'));
        self::assertSame(['ROLE_READER'], $route->getDefault('_derafu_content_roles'));
        self::assertSame(['GET'], $route->getMethods());
        $callable = ['InvoiceController', 'show'];
        self::assertSame($callable, (new RouteConverter())->convert(new Route('callable', '/', $callable))->getDefault('_controller'));
    }

    public function testGenerationUsesTheSharedSymfonyContextForEveryReference(): void
    {
        $routes = new RouteCollection();
        $routes->add('page', new SymfonyRoute('/knowledge/pages/{page}'));
        $nativeContext = new SymfonyContext();
        $generator = new UrlGenerator(new SymfonyGenerator($routes, $nativeContext), $nativeContext);
        $context = new RequestContext('/app', 'POST', 'example.test', 'https', 8080, 8443, '/knowledge/docs/guide', 'q=hello');
        self::assertSame($generator, $generator->setContext($context));
        $converted = $generator->getContext();
        self::assertSame($context->getBaseUrl(), $converted->getBaseUrl());
        self::assertSame($context->getMethod(), $converted->getMethod());
        self::assertSame($context->getHost(), $converted->getHost());
        self::assertSame($context->getScheme(), $converted->getScheme());
        self::assertSame($context->getHttpPort(), $converted->getHttpPort());
        self::assertSame($context->getHttpsPort(), $converted->getHttpsPort());
        self::assertSame($context->getPathInfo(), $converted->getPathInfo());
        self::assertSame($context->getQueryString(), $converted->getQueryString());
        self::assertSame('/app/knowledge/pages/about', $generator->generate('page', ['page' => 'about']));
        self::assertSame('https://example.test:8443/app/knowledge/pages/about', $generator->generate('page', ['page' => 'about'], UrlReferenceType::ABSOLUTE_URL));
        self::assertSame('//example.test:8443/app/knowledge/pages/about', $generator->generate('page', ['page' => 'about'], UrlReferenceType::NETWORK_PATH));
        self::assertSame('../pages/about', $generator->generate('page', ['page' => 'about'], UrlReferenceType::RELATIVE_PATH));
        $this->expectException(UrlGeneratorException::class);
        $generator->generate('missing');
    }

    public function testCollectionIsScopedAndRestoredAfterPluginFailure(): void
    {
        $registrar = new RouteRegistrar();
        $registrar->addRoute('outside', '/outside', 'Controller::show');
        $routes = $registrar->collect(fn () => $registrar->addRoute('inside', '/inside', 'Controller::show'));
        self::assertCount(1, $routes);
        self::assertSame('inside', $routes[0]->getName());
        try {
            $registrar->collect(fn () => $registrar->collect(static fn () => null));
            self::fail('Nested collection must fail.');
        } catch (TranslatableLogicException $exception) {
            self::assertSame('Content route collection is already in progress.', $exception->getMessage());
        }
        self::assertSame([], $registrar->collect(static fn () => null));
    }
}
