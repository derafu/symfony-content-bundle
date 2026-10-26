<?php

declare(strict_types=1);

namespace Derafu\TestsContentBundle;

use Derafu\TestsContentBundle\Fixture\Kernel;
use Derafu\Translation\Contract\TranslatableInterface;
use Mcp\Server;
use Mpdf\Mpdf;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;
use Utopia\OpenAPI\Parser;

require dirname(__DIR__) . '/vendor/autoload.php';
// Composer omits the test namespace when installed with --no-dev.
require __DIR__ . '/fixtures/Kernel.php';
require __DIR__ . '/fixtures/FixtureController.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function requiresPackage(Kernel $kernel, Request $request, string $package): void
{
    try {
        $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, false);
    } catch (Throwable $error) {
        // Twig wraps errors raised while evaluating content metadata.
        $cause = $error;
        while (!$cause instanceof TranslatableInterface && $cause->getPrevious() !== null) {
            $cause = $cause->getPrevious();
        }
        check($cause instanceof TranslatableInterface, 'An optional dependency error must retain its translatable cause: ' . $error->getMessage());
        check(str_contains(strtolower($error->getMessage()), strtolower($package)), 'The error must identify the missing dependency: ' . $error->getMessage());
        return;
    }
    throw new RuntimeException('Expected a missing optional dependency error for ' . $package);
}

check(!class_exists(Server::class) && !class_exists(Mpdf::class) && !class_exists(Parser::class), 'Run this check after composer install --no-dev.');
$filesystem = new Filesystem();
$project = sys_get_temp_dir() . '/derafu-content-runtime-' . bin2hex(random_bytes(8));
$filesystem->mirror(__DIR__ . '/fixtures/site', $project);
$kernel = new Kernel($project, ['content' => ['plugins' => ['pages' => null, 'docs' => null]]], false);
$exitCode = 0;
try {
    check($kernel->handle(Request::create('/knowledge/pages/about'))->getStatusCode() === 200, 'HTML must work without optional libraries.');
    check($kernel->handle(Request::create('/knowledge/pages/about.md'))->getStatusCode() === 200, 'Markdown must work without optional libraries.');
    check($kernel->handle(Request::create('/knowledge/pages/about.json'))->getStatusCode() === 200, 'JSON must work without optional libraries.');
    check($kernel->handle(Request::create('/knowledge/api/content/mcp', 'POST'))->getStatusCode() === 404, 'A disabled MCP plugin must retain its 404.');
    requiresPackage($kernel, Request::create('/knowledge/pages/about.pdf'), 'Mpdf');
    $filesystem->mkdir($project . '/resources/content/docs/api/_attachments');
    file_put_contents($project . '/resources/content/docs/api.md', "---\ntitle: API\nopenapi: '?attachment=spec.yaml'\n---\nAPI documentation.\n");
    file_put_contents($project . '/resources/content/docs/api/_attachments/spec.yaml', "openapi: 3.0.3\ninfo: {title: Example, version: '1.0'}\npaths: {}\n");
    $kernel->shutdown();
    $filesystem->remove($project . '/var');
    $kernel = new Kernel($project, ['content' => ['plugins' => ['pages' => null, 'docs' => null, 'mcp' => null]]], false);
    check($kernel->handle(Request::create('/knowledge/pages/about'))->getStatusCode() === 200, 'Enabling MCP must not require its SDK on unrelated requests.');
    requiresPackage($kernel, Request::create('/knowledge/api/content/mcp', 'POST'), 'mcp/sdk');
    requiresPackage($kernel, Request::create('/knowledge/docs/api.md'), 'utopia-php/openapi');
    echo "Runtime checks passed without mPDF, MCP SDK or OpenAPI.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    $kernel->shutdown();
    $filesystem->remove($project);
}

exit($exitCode);
