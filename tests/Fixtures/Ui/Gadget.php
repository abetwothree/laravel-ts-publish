<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui;

use Workbench\App\Packages\Ui\HasIcon;
use Workbench\App\Packages\Ui\Node;

/**
 * A composed class outside every scanned directory, so only a value that reaches it gets its module written.
 */
final class Gadget extends Node implements HasIcon {}
