<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use Workbench\App\Packages\Ui\HasIcon;
use Workbench\App\Packages\Ui\Node;

/**
 * A composed class in a controller's namespace, so its module's barrel is the route barrel's directory.
 */
final class UiGadgetWidget extends Node implements HasIcon {}
