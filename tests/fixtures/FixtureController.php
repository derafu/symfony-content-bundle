<?php

declare(strict_types=1);

namespace Derafu\TestsContentBundle\Fixture;

use Derafu\Http\Request;
use Derafu\Renderer\Contract\RendererInterface;
use Nyholm\Psr7\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/** A native application controller consuming the shared renderer. */
final class FixtureController
{
    public function __construct(private readonly RendererInterface $renderer)
    {
    }

    public function getRenderer(): RendererInterface
    {
        return $this->renderer;
    }

    public function native(): SymfonyResponse
    {
        return new SymfonyResponse('Native response', 202);
    }

    /** @return array<string, mixed> */
    public function echo(Request $request): array
    {
        return ['method' => $request->getMethod(), 'query' => $request->query('q'), 'posted' => $request->post('name'), 'cookie' => $request->getCookieParams()['session'] ?? null];
    }

    public function attachment(): Response
    {
        return new Response(206, ['Content-Type' => 'application/octet-stream', 'Content-Disposition' => 'attachment; filename="fixture.bin"', 'Set-Cookie' => ['first=1; Path=/', 'second=2; HttpOnly']], 'binary' . chr(0) . 'data');
    }
}
