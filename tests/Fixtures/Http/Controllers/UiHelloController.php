<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use Workbench\App\Packages\Ui\Concerns\AnnouncesHello;
use Workbench\App\Packages\Ui\HasIcon;

/**
 * A routed controller with a trait's and an interface's override, which would compose were its file not the route's.
 */
final class UiHelloController implements HasIcon
{
    use AnnouncesHello;

    /**
     * Greets the caller.
     */
    public function index(): string
    {
        return $this->hello();
    }
}
