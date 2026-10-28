<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Packages\Ui\Concerns\AnnouncesHello;

/**
 * A model, which its own publisher writes, with an override of its own and a trait's.
 */
#[TsType(['type' => 'UiNode', 'import' => '@js/types/ui'])]
class AnnouncingPost extends Model
{
    use AnnouncesHello;
}
