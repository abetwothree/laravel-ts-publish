<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Workbench\App\Packages\Ui\Concerns\AnnouncesHello;

/**
 * A model, which its own publisher writes, with a trait's override and none of its own.
 */
class AnnouncingDraft extends Model
{
    use AnnouncesHello;
}
