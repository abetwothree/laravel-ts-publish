<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use Workbench\App\Packages\Ui\Concerns\AnnouncesHello;
use Workbench\App\Packages\Ui\HasIcon;
use Workbench\App\Packages\Ui\Node;

/**
 * A routed controller whose parent class declares an override, beside a trait's and an interface's.
 */
final class UiNodeController extends Node implements HasIcon
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
