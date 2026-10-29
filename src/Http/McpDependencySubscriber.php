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

use Derafu\Content\Contract\ContentServiceInterface;
use Derafu\Content\Plugin\Mcp\McpController;
use Derafu\Translation\Exception\Core\TranslatableLogicException;
use Mcp\Server;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Checks the optional SDK when the MCP action is about to run. */
final class McpDependencySubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly ContentServiceInterface $contentService)
    {
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER => 'onController'];
    }

    public function onController(ControllerEvent $event): void
    {
        $controller = $event->getController();
        if ($event->getRequest()->attributes->get('_derafu_content') !== true
            || !is_array($controller) || !$controller[0] instanceof McpController
            || class_exists(Server::class)) {
            return;
        }
        // A disabled plugin still has its normal 404, regardless of SDKs.
        $this->contentService->plugin('mcp');
        throw new TranslatableLogicException('The MCP endpoint requires mcp/sdk. Run: composer require mcp/sdk');
    }
}
