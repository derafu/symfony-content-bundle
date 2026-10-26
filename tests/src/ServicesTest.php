<?php

declare(strict_types=1);

namespace Derafu\TestsContentBundle;

use Derafu\Content\Contract\ContentServiceInterface;
use Derafu\Content\Plugin\Academy\AcademyController;
use Derafu\Content\Plugin\Api\ApiController;
use Derafu\Content\Plugin\Blog\BlogController;
use Derafu\Content\Plugin\Docs\DocsController;
use Derafu\Content\Plugin\Faq\FaqController;
use Derafu\Content\Plugin\Mcp\McpController;
use Derafu\Content\Plugin\Pages\PagesController;
use Derafu\Content\Plugin\Search\SearchController;
use Derafu\Content\Plugin\Sitemap\SitemapController;
use Derafu\Content\Plugin\Storage\StorageController;
use Derafu\ContentBundle\ContentBundle;
use Derafu\ContentBundle\DependencyInjection\ContentPass;
use Derafu\ContentBundle\Http\ContentSubscriber;
use Derafu\ContentBundle\Http\McpDependencySubscriber;
use Derafu\ContentBundle\Http\RequestConverter;
use Derafu\ContentBundle\Http\RequestValueResolver;
use Derafu\ContentBundle\Renderer\RendererFactory;
use Derafu\ContentBundle\Routing\ContentRouteLoader;
use Derafu\ContentBundle\Routing\RouteConverter;
use Derafu\ContentBundle\Routing\RouteRegistrar;
use Derafu\ContentBundle\Routing\UrlGenerator;
use Derafu\ContentBundle\Translation\ContentBundleTranslationResourceProvider;
use Derafu\ContentBundle\Twig\ContentExtension;
use Derafu\Renderer\Contract\RendererInterface;
use Derafu\Renderer\Engine\Html\TwigHtmlEngine;
use Derafu\Routing\Contract\UrlGeneratorInterface;
use Derafu\TestsContentBundle\Fixture\Kernel;
use Derafu\TestsContentBundle\Fixture\RoutePagesPlugin;
use Derafu\Translation\Exception\Core\TranslatableLogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[CoversClass(ContentBundle::class)]
#[CoversClass(McpDependencySubscriber::class)]
#[CoversClass(ContentBundleTranslationResourceProvider::class)]
#[CoversClass(ContentPass::class)]
#[CoversClass(ContentSubscriber::class)]
#[CoversClass(RequestConverter::class)]
#[CoversClass(RequestValueResolver::class)]
#[CoversClass(RendererFactory::class)]
#[CoversClass(ContentRouteLoader::class)]
#[CoversClass(RouteConverter::class)]
#[CoversClass(RouteRegistrar::class)]
#[CoversClass(UrlGenerator::class)]
#[CoversClass(ContentExtension::class)]
final class ServicesTest extends TestCase
{
    private string $project;

    private Kernel $kernel;

    /** @var array<array-key, mixed> */
    private array $environment;

    /** @var array<array-key, mixed> */
    private array $server;

    private string|false $verbosity;

    protected function setUp(): void
    {
        $this->environment = $_ENV;
        $this->server = $_SERVER;
        $this->verbosity = getenv('SHELL_VERBOSITY');
        $this->project = sys_get_temp_dir() . '/derafu-content-bundle-' . bin2hex(random_bytes(8));
        (new Filesystem())->mirror(__DIR__ . '/../fixtures/site', $this->project);
        $this->kernel = new Kernel($this->project, ['content' => [
            'title' => 'Fixture site', 'url' => 'https://example.test',
            'cache' => ['enabled' => false],
            'plugins' => ['pages' => ['class' => RoutePagesPlugin::class], 'docs' => null, 'blog' => null, 'sitemap' => null, 'storage' => null],
        ]]);
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
        (new Filesystem())->remove($this->project);
        $_ENV = $this->environment;
        $_SERVER = $this->server;
        putenv($this->verbosity === false ? 'SHELL_VERBOSITY' : 'SHELL_VERBOSITY=' . $this->verbosity);
    }

    public function testRoutesKeepNamesRequirementsAndImportPrefix(): void
    {
        $this->kernel->boot();
        $router = $this->kernel->getContainer()->get('test.router');
        self::assertInstanceOf(RouterInterface::class, $router);
        self::assertSame('/knowledge/pages/team/member', $router->generate('pages_page', ['page' => 'team/member']));
        self::assertSame('pages_page', $router->match('/knowledge/pages/team/member')['_route']);
        $mcp = $router->getRouteCollection()->get('content_mcp');
        self::assertNotNull($mcp);
        self::assertTrue($mcp->getDefault('_derafu_content'));
        self::assertSame(['POST', 'DELETE', 'OPTIONS'], $mcp->getMethods());
    }

    public function testHtmlUsesApplicationTwigAndLayout(): void
    {
        $response = $this->kernel->handle(Request::create('/knowledge/pages/about'));
        self::assertSame(200, $response->getStatusCode(), $response->getContent() ?: '');
        self::assertStringContainsString('Application layout', $response->getContent() ?: '');
        self::assertStringContainsString('<strong>useful</strong>', $response->getContent() ?: '');
        self::assertStringContainsString('<title>About the application</title>', $response->getContent() ?: '');
    }

    public function testJsonAndMarkdownUseContentNegotiationAndGeneratedLinks(): void
    {
        $response = $this->kernel->handle(Request::create('/knowledge/pages/about.json'));
        self::assertSame(200, $response->getStatusCode(), $response->getContent() ?: '');
        self::assertStringContainsString('application/json', $response->headers->get('Content-Type') ?? '');
        self::assertStringContainsString('/knowledge/pages/about', $response->getContent() ?: '');
        $markdown = $this->kernel->handle(Request::create('/knowledge/pages/about.md'));
        self::assertSame(200, $markdown->getStatusCode());
        self::assertStringContainsString('text/markdown', $markdown->headers->get('Content-Type') ?? '');
        self::assertStringContainsString('**useful**', $markdown->getContent() ?: '');
    }

    public function testMissingContentAndDisabledPluginReturn404(): void
    {
        self::assertSame(404, $this->kernel->handle(Request::create('/knowledge/pages/missing'))->getStatusCode());
        self::assertSame(404, $this->kernel->handle(Request::create('/knowledge/faq'))->getStatusCode());
    }

    public function testNativeControllersAreUnaffected(): void
    {
        $response = $this->kernel->handle(Request::create('/native'));
        self::assertSame(202, $response->getStatusCode());
        self::assertSame('Native response', $response->getContent());
    }

    public function testComponentsMarkdownAndNativeFunctionsShareTheEnvironment(): void
    {
        $this->kernel->boot();
        $twig = $this->kernel->getContainer()->get('test.twig');
        self::assertInstanceOf(Environment::class, $twig);
        $html = $twig->createTemplate('{{ path("pages_page", {page: "about"}) }} {{ "**bold**"|markdown }} <twig:block-alert content="A component" dismissible />')->render();
        self::assertStringContainsString('/knowledge/pages/about', $html);
        self::assertStringContainsString('<strong>bold</strong>', $html);
        self::assertStringContainsString('A component', $html);
        self::assertFalse($twig->getExtension(ContentExtension::class)->isActivePath('pages_page', ['page' => 'about']));
    }

    public function testPluginRoutesAreCompiledAndRequestValuesArePreserved(): void
    {
        $request = Request::create('/knowledge/plugin/echo?q=filter', 'POST', ['name' => 'Example'], ['session' => 'cookie-value']);
        $response = $this->kernel->handle($request);
        self::assertSame(200, $response->getStatusCode(), $response->getContent() ?: '');
        self::assertSame(['method' => 'POST', 'query' => 'filter', 'posted' => 'Example', 'cookie' => 'cookie-value'], json_decode($response->getContent() ?: '', true, flags: JSON_THROW_ON_ERROR));
        // Replaying plugin initialization during a request keeps compiled routes.
        $content = $this->kernel->getContainer()->get('test.' . ContentServiceInterface::class);
        self::assertInstanceOf(ContentServiceInterface::class, $content);
        self::assertArrayHasKey('pages', $content->plugins());
        self::assertSame(405, $this->kernel->handle(Request::create('/knowledge/plugin/echo'))->getStatusCode());
    }

    public function testPsrAttachmentPreservesStatusCookiesHeadersAndBinaryBody(): void
    {
        $response = $this->kernel->handle(Request::create('/knowledge/plugin/attachment'));
        self::assertSame(206, $response->getStatusCode());
        self::assertSame('binary' . chr(0) . 'data', $response->getContent());
        self::assertSame('attachment; filename="fixture.bin"', $response->headers->get('Content-Disposition'));
        self::assertCount(2, $response->headers->getCookies());
    }

    public function testRssPreservesCacheHeadersAndConditional304(): void
    {
        $response = $this->kernel->handle(Request::create('/knowledge/blog/rss.xml'));
        self::assertSame(200, $response->getStatusCode(), $response->getContent() ?: '');
        self::assertStringContainsString('application/rss+xml', $response->headers->get('Content-Type') ?? '');
        self::assertStringContainsString('Welcome to the blog', $response->getContent() ?: '');
        self::assertTrue($response->headers->has('ETag'));
        $request = Request::create('/knowledge/blog/rss.xml');
        $request->headers->set('If-None-Match', $response->headers->get('ETag') ?? '');
        $cached = $this->kernel->handle($request);
        self::assertSame(304, $cached->getStatusCode());
        self::assertEmpty($cached->getContent());
    }

    public function testSitemapUsesXmlAndPrefixedContentLinks(): void
    {
        $response = $this->kernel->handle(Request::create('/knowledge/sitemap.xml'));
        self::assertSame(200, $response->getStatusCode(), $response->getContent() ?: '');
        self::assertStringContainsString('xml', $response->headers->get('Content-Type') ?? '');
        self::assertStringContainsString('/knowledge/pages/about', $response->getContent() ?: '');
    }

    public function testApplicationCanMountRoutesWithoutPrefixAndOverrideLayout(): void
    {
        $this->kernel->shutdown();
        (new Filesystem())->remove($this->project . '/var');
        file_put_contents($this->project . '/routes.yaml', "content:\n    resource: .\n    type: derafu_content\n");
        file_put_contents($this->project . '/templates/custom.html.twig', 'Custom layout: {% block body %}{% endblock %}');
        $this->kernel = new Kernel($this->project, ['layout' => 'custom.html.twig', 'content' => ['plugins' => ['pages' => null]]]);
        $response = $this->kernel->handle(Request::create('/pages/about'));
        self::assertSame(200, $response->getStatusCode(), $response->getContent() ?: '');
        self::assertStringStartsWith('Custom layout:', $response->getContent() ?: '');
    }

    public function testWarmedProductionRoutesCanBeReusedByAnotherKernel(): void
    {
        $this->kernel->shutdown();
        $options = ['content' => ['plugins' => ['pages' => ['class' => RoutePagesPlugin::class]]]];
        $this->kernel = new Kernel($this->project, $options, false);
        $this->kernel->boot();
        $router = $this->kernel->getContainer()->get('test.router');
        self::assertInstanceOf(RouterInterface::class, $router);
        self::assertSame('/knowledge/plugin/attachment', $router->generate('plugin_attachment'));
        $this->kernel->shutdown();
        $this->kernel = new Kernel($this->project, $options, false);
        $response = $this->kernel->handle(Request::create('/knowledge/plugin/attachment'));
        self::assertSame(206, $response->getStatusCode());
    }

    public function testPdfExportUsesTheRealMpdfEngine(): void
    {
        $response = $this->kernel->handle(Request::create('/knowledge/pages/about.pdf'));
        self::assertSame(200, $response->getStatusCode(), $response->getContent() ?: '');
        self::assertStringContainsString('application/pdf', $response->headers->get('Content-Type') ?? '');
        self::assertStringStartsWith('%PDF-', $response->getContent() ?: '');
    }

    public function testSearchInvalidInputMapsTo400WithoutContactingABackend(): void
    {
        self::assertSame(400, $this->kernel->handle(Request::create('/knowledge/api/content/search.json?q[]=invalid'))->getStatusCode());
        self::assertSame(400, $this->kernel->handle(Request::create('/knowledge/api/content/search.json'))->getStatusCode());
    }

    public function testDefaultsBootAndLoadRoutesWithoutApplicationContent(): void
    {
        $this->kernel->shutdown();
        (new Filesystem())->remove([$this->project . '/var', $this->project . '/resources/content']);
        $this->kernel = new Kernel($this->project);
        $this->kernel->boot();
        $router = $this->kernel->getContainer()->get('test.router');
        self::assertInstanceOf(RouterInterface::class, $router);
        self::assertSame('/knowledge/docs', $router->generate('docs'));
        self::assertSame(404, $this->kernel->handle(Request::create('/knowledge/docs/missing'))->getStatusCode());
    }

    public function testMcpInitializationTraversesBothHttpBridges(): void
    {
        $this->kernel->shutdown();
        (new Filesystem())->remove($this->project . '/var');
        $this->kernel = new Kernel($this->project, ['content' => ['plugins' => [
            'mcp' => ['session_path' => $this->project . '/sessions'], 'pages' => null,
        ]]]);
        $payload = json_encode([
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize',
            'params' => ['protocolVersion' => '2025-06-18', 'capabilities' => new \stdClass(), 'clientInfo' => ['name' => 'bundle-test', 'version' => '1.0']],
        ], JSON_THROW_ON_ERROR);
        $request = Request::create('http://localhost/knowledge/api/content/mcp', 'POST', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream'], content: $payload);
        $response = $this->kernel->handle($request);
        self::assertSame(200, $response->getStatusCode(), $response->getContent() ?: '');
        self::assertNotEmpty($response->headers->get('Mcp-Session-Id'));
        self::assertStringContainsString('protocolVersion', $response->getContent() ?: '');
    }

    public function testServicesAndAliasesArePrivateAndUseTheApplicationEnvironment(): void
    {
        $this->kernel->boot();
        $container = $this->kernel->getContainer();
        self::assertFalse($container->has(ContentServiceInterface::class));
        self::assertFalse($container->has(UrlGeneratorInterface::class));
        $content = $container->get('test.' . ContentServiceInterface::class);
        self::assertInstanceOf(ContentServiceInterface::class, $content);
        self::assertSame($content, $container->get('test.' . ContentServiceInterface::class));
        foreach ([AcademyController::class, ApiController::class, BlogController::class, DocsController::class, FaqController::class, McpController::class, PagesController::class, SearchController::class, SitemapController::class, StorageController::class] as $class) {
            self::assertInstanceOf($class, $container->get($class));
        }
        $renderer = $container->get('test.derafu_content.renderer');
        self::assertInstanceOf(RendererInterface::class, $renderer);
        $engine = $renderer->getEngine('twig');
        self::assertInstanceOf(TwigHtmlEngine::class, $engine);
        self::assertSame($container->get('test.twig'), $engine->getTwig());
        self::assertNotSame($renderer, $container->get('test.app.renderer'));
        self::assertSame('Fixture site', $content->context()->config()->title());
    }

    public function testTaggedTranslationsReachSymfonyAndApplicationOverridesWin(): void
    {
        mkdir($this->project . '/translations');
        file_put_contents($this->project . '/translations/content+intl-icu.es.yaml', "PDF: 'Application PDF'\n");
        $this->kernel->boot();
        $translator = $this->kernel->getContainer()->get('test.translator');
        self::assertInstanceOf(TranslatorInterface::class, $translator);
        self::assertSame('Application PDF', $translator->trans('PDF', [], 'content', 'es'));
        self::assertSame('Cerrar', $translator->trans('Close', [], 'twig', 'es'));
        try {
            $registrar = new RouteRegistrar();
            $registrar->collect(fn () => $registrar->collect(static fn () => null));
            self::fail('Nested route collection must fail.');
        } catch (TranslatableLogicException $exception) {
            self::assertSame('La recopilación de rutas de contenido ya está en curso.', $exception->trans($translator, 'es'));
        }
    }

    public function testApplicationTemplatesOverridePackageTemplates(): void
    {
        mkdir($this->project . '/templates/pages');
        file_put_contents($this->project . '/templates/pages/show.html.twig', "{% extends 'layouts/default.html.twig' %}{% block content %}An application override: {{ page.title }}{% endblock %}");
        $response = $this->kernel->handle(Request::create('/knowledge/pages/about'));
        self::assertSame(200, $response->getStatusCode(), $response->getContent() ?: '');
        self::assertStringContainsString('Application layout', $response->getContent() ?: '');
        self::assertStringContainsString('An application override: About the application', $response->getContent() ?: '');
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidConfiguration(): iterable
    {
        yield 'empty layout' => [['layout' => '']];
        yield 'non-string layout' => [['layout' => 5]];
        yield 'non-array content' => [['content' => 'invalid']];
        yield 'unknown option' => [['unknown' => true]];
    }

    /** @param array<string, mixed> $options */
    #[DataProvider('invalidConfiguration')]
    public function testInvalidBundleConfigurationFailsDuringCompilation(array $options): void
    {
        $this->kernel->shutdown();
        $this->kernel = new Kernel($this->project, $options);
        $this->expectException(InvalidConfigurationException::class);
        $this->kernel->boot();
    }

    public function testContentFilesInvalidateTheCompiledPluginRoutes(): void
    {
        $file = $this->project . '/resources/content/pages/about.md';
        file_put_contents($file, "---\ntitle: Dynamic page\nroute_path: /dynamic/original\n---\nContent body.\n");
        $this->kernel->boot();
        $router = $this->kernel->getContainer()->get('test.router');
        self::assertInstanceOf(RouterInterface::class, $router);
        self::assertSame('/knowledge/dynamic/original', $router->generate('plugin_page'));
        $this->kernel->shutdown();
        file_put_contents($file, "---\ntitle: Dynamic page\nroute_path: /dynamic/updated\n---\nContent body.\n");
        // Filesystems can have second-resolution mtimes. Ensure freshness sees
        // the edit without introducing a timing-dependent sleep into the test.
        touch($file, time() + 5);
        clearstatcache();
        $this->kernel->boot();
        $updated = $this->kernel->getContainer()->get('test.router');
        self::assertInstanceOf(RouterInterface::class, $updated);
        self::assertSame('/knowledge/dynamic/updated', $updated->generate('plugin_page'));
    }

    public function testOpenApiReferencesRenderWithTheRealParser(): void
    {
        $response = $this->kernel->handle(Request::create('/knowledge/docs/api.md'));
        self::assertSame(200, $response->getStatusCode(), $response->getContent() ?: '');
        self::assertStringContainsString('/widgets', $response->getContent() ?: '');
        self::assertStringContainsString('List widgets', $response->getContent() ?: '');
        $pdf = $this->kernel->handle(Request::create('/knowledge/docs/api.pdf'));
        self::assertSame(200, $pdf->getStatusCode(), $pdf->getContent() ?: '');
        self::assertStringStartsWith('%PDF-', $pdf->getContent() ?: '');
    }
}
