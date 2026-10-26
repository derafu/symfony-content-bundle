<?php

declare(strict_types=1);

namespace Derafu\TestsContentBundle;

use Derafu\Content\Plugin\Search\Exception\SearchUpstreamException;
use Derafu\ContentBundle\Http\ContentSubscriber;
use Derafu\ContentBundle\Http\RequestConverter;
use Derafu\ContentBundle\Http\RequestValueResolver;
use Derafu\ContentBundle\Twig\ContentExtension;
use Derafu\Http\Contract\RequestInterface;
use Derafu\Http\Request as ContentRequest;
use Derafu\Http\Service\ResponseNormalizer;
use Nyholm\Psr7\Response as PsrResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Translation\Translator;

#[CoversClass(RequestConverter::class)]
#[CoversClass(RequestValueResolver::class)]
#[CoversClass(ContentSubscriber::class)]
#[CoversClass(ContentExtension::class)]
final class HttpTest extends TestCase
{
    public function testRequestConversionPreservesUploadsBodyAndAttributesAndReusesTheRequest(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'content-upload-');
        file_put_contents($file, 'An uploaded document');
        try {
            $request = Request::create('https://example.test/upload?q=search', 'POST', ['name' => 'Example'], ['session' => 'cookie'], ['document' => new UploadedFile($file, 'document.txt', 'text/plain', test: true)], ['HTTP_X_CUSTOM' => 'header'], 'raw body');
            $request->attributes->set('custom', 'attribute');
            $request->attributes->set('_derafu_content', true);
            $converter = new RequestConverter();
            $converted = $converter->convert($request);
            self::assertSame($converted, $converter->convert($request));
            self::assertSame('raw body', (string) $converted->getBody());
            self::assertSame('header', $converted->getHeaderLine('X-Custom'));
            self::assertSame('attribute', $converted->getAttribute('custom'));
            self::assertSame('search', $converted->query('q'));
            self::assertSame('Example', $converted->post('name'));
            $upload = $converted->file('document');
            self::assertNotNull($upload);
            self::assertSame('document.txt', $upload->getClientFilename());
            self::assertSame('An uploaded document', (string) $upload->getStream());
            $resolver = new RequestValueResolver($converter);
            foreach ([ContentRequest::class, RequestInterface::class] as $class) {
                self::assertSame([$converted], iterator_to_array($resolver->resolve($request, new ArgumentMetadata('request', $class, false, false, null))));
            }
            self::assertSame([], iterator_to_array($resolver->resolve($request, new ArgumentMetadata('request', Request::class, false, false, null))));
            $request->attributes->remove('_derafu_content');
            self::assertSame([], iterator_to_array($resolver->resolve($request, new ArgumentMetadata('request', ContentRequest::class, false, false, null))));
        } finally {
            unlink($file);
        }
    }

    public function testSubscriberLeavesUnrelatedViewsAndExceptionsUnchanged(): void
    {
        $kernel = new HttpKernel(new EventDispatcher(), new ControllerResolver());
        $request = Request::create('/native');
        $subscriber = new ContentSubscriber(new RequestConverter(), new ResponseNormalizer(), new Translator('en'));
        $view = new ViewEvent($kernel, $request, HttpKernel::MAIN_REQUEST, ['native' => true]);
        $subscriber->onView($view);
        self::assertNull($view->getResponse());
        $exception = new \RuntimeException('Application failure', 400);
        $event = new ExceptionEvent($kernel, $request, HttpKernel::MAIN_REQUEST, $exception);
        $subscriber->onException($event);
        self::assertSame($exception, $event->getThrowable());
        $request->attributes->set('_derafu_content', true);
        $subscriber->onException($event);
        self::assertSame($exception, $event->getThrowable());
    }

    public function testActivePathUsesSegmentBoundariesAndSupportsExactMatching(): void
    {
        $routes = new RouteCollection();
        $routes->add('docs', new Route('/knowledge/docs'));
        $requests = new RequestStack();
        $extension = new ContentExtension(new UrlGenerator($routes, new RequestContext()), $requests);
        self::assertFalse($extension->isActivePath('docs'));
        $requests->push(Request::create('/knowledge/docs/guide'));
        self::assertTrue($extension->isActivePath('docs'));
        self::assertFalse($extension->isActivePath('docs', partial: false));
        $requests->pop();
        $requests->push(Request::create('/knowledge/docs/'));
        self::assertTrue($extension->isActivePath('docs', partial: false));
        $requests->pop();
        $requests->push(Request::create('/knowledge/docs-other'));
        self::assertFalse($extension->isActivePath('docs'));
    }

    public function testNonSeekablePsrBodiesRemainStreamed(): void
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($pair === false) {
            self::fail('Unable to create a local stream pair.');
        }
        [$writer, $reader] = $pair;
        try {
            fwrite($writer, "data: a streamed event\n\n");
            stream_socket_shutdown($writer, STREAM_SHUT_WR);
            $psr = new PsrResponse(200, ['Content-Type' => 'text/event-stream'], $reader);
            self::assertFalse($psr->getBody()->isSeekable());
            $request = Request::create('/events');
            $request->attributes->set('_derafu_content', true);
            $kernel = new HttpKernel(new EventDispatcher(), new ControllerResolver());
            $event = new ViewEvent($kernel, $request, HttpKernel::MAIN_REQUEST, $psr);
            $subscriber = new ContentSubscriber(new RequestConverter(), new ResponseNormalizer(), new Translator('en'));
            $subscriber->onView($event);
            $response = $event->getResponse();
            self::assertInstanceOf(StreamedResponse::class, $response);
            self::assertStringContainsString('text/event-stream', $response->headers->get('Content-Type') ?? '');
            $callback = $response->getCallback();
            self::assertNotNull($callback);
            ob_start();
            try {
                $callback();
                self::assertSame("data: a streamed event\n\n", ob_get_contents());
            } finally {
                ob_end_clean();
            }
        } finally {
            fclose($writer);
            fclose($reader);
        }
    }

    public function testHttpExceptionsKeepStatusHeadersAndOriginalCause(): void
    {
        $exception = new SearchUpstreamException('Search is unavailable.');
        $exception->setHeaders(['Retry-After' => '60']);
        $request = Request::create('/search');
        $request->attributes->set('_derafu_content', true);
        $kernel = new HttpKernel(new EventDispatcher(), new ControllerResolver());
        $event = new ExceptionEvent($kernel, $request, HttpKernel::MAIN_REQUEST, $exception);
        (new ContentSubscriber(new RequestConverter(), new ResponseNormalizer(), new Translator('en')))->onException($event);
        $mapped = $event->getThrowable();
        self::assertInstanceOf(HttpException::class, $mapped);
        self::assertSame(502, $mapped->getStatusCode());
        self::assertSame(['Retry-After' => '60'], $mapped->getHeaders());
        self::assertSame($exception, $mapped->getPrevious());
    }
}
