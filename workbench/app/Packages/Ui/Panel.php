<?php

declare(strict_types=1);

namespace Workbench\App\Packages\Ui;

/**
 * A node with no override of its own, so it publishes its parent's: the example of #136.
 */
final class Panel extends Node {}
