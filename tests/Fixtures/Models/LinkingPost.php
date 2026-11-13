<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Workbench\App\Models\Comment;

/** A post binding `LinksComments`' template by `@use`; that trait and `Commentable` share its namespace, unimported. */
class LinkingPost extends Model implements Commentable
{
    /** @use LinksComments<Comment> */
    use LinksComments;

    protected $table = 'posts';

    protected $guarded = [];

    protected $appends = ['linked_items', 'first_linked', 'latest'];
}
