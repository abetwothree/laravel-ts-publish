<?php

declare(strict_types=1);

namespace Workbench\App\Packages\Ui\Concerns;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;

/**
 * A trait whose override every using class inherits.
 */
#[TsType(['type' => 'UiHello', 'import' => '@js/types/ui'])]
trait AnnouncesHello
{
    public function hello(): string
    {
        return 'hello';
    }
}
