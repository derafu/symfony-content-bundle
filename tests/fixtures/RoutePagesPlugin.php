<?php

declare(strict_types=1);

namespace Derafu\TestsContentBundle\Fixture;

use Derafu\Content\Contract\ContentBagInterface;
use Derafu\Content\Plugin\Pages\PagesPlugin;

/** A genuine custom plugin publishing routes from its content-loaded hook. */
final class RoutePagesPlugin extends PagesPlugin
{
    public function contentLoaded(ContentBagInterface $bag, array $actions): void
    {
        parent::contentLoaded($bag, $actions);
        $actions['addRoute'](['name' => 'plugin_echo', 'path' => '/plugin/echo', 'handler' => FixtureController::class . '::echo', 'methods' => ['POST']]);
        $actions['addRoute'](['name' => 'plugin_attachment', 'path' => '/plugin/attachment', 'handler' => FixtureController::class . '::attachment']);

        $path = $this->registry()->get('about')->metadata('route_path');
        if (is_string($path)) {
            $actions['addRoute'](['name' => 'plugin_page', 'path' => $path, 'handler' => FixtureController::class . '::native']);
        }
    }
}
