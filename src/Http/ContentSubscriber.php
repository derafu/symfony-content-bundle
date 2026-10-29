<?php

declare(strict_types=1);

/**
 * Derafu: Symfony Content Bundle - Content publishing with Symfony.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\ContentBundle\Http;

use Derafu\Content\Plugin\Search\SearchController;
use Derafu\Http\Contract\HttpExceptionInterface;
use Derafu\Http\Contract\ResponseNormalizerInterface;
use InvalidArgumentException;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Adapts only routes explicitly imported through the Content loader. */
final class ContentSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RequestConverter $converter,
        private readonly ResponseNormalizerInterface $normalizer,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::VIEW => 'onView', KernelEvents::EXCEPTION => ['onException', 64]];
    }

    public function onView(ViewEvent $event): void
    {
        if ($event->getRequest()->attributes->get('_derafu_content') !== true) {
            return;
        }
        $response = $this->normalizer->normalize($this->converter->convert($event->getRequest()), $event->getControllerResult());
        // Non-seekable bodies must remain streams instead of being consumed
        // while converting the controller result.
        $event->setResponse((new HttpFoundationFactory())->createResponse($response, streamed: !$response->getBody()->isSeekable()));
    }

    public function onException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->attributes->get('_derafu_content') !== true) {
            return;
        }
        $exception = $event->getThrowable();
        if ($exception instanceof HttpExceptionInterface) {
            $event->setThrowable(new HttpException($exception->getStatus()->value, $exception->trans($this->translator, $request->getLocale()), $exception, $exception->getHeaders()));
        } elseif ($exception instanceof InvalidArgumentException && $exception->getCode() === 400
            && is_string($controller = $request->attributes->get('_controller'))
            && str_starts_with($controller, SearchController::class . '::')) {
            $event->setThrowable(new HttpException(400, $exception->getMessage(), $exception));
        }
    }
}
