<?php

declare(strict_types=1);

namespace Workbench\Blog\Enums;

enum ContentType: string
{
    case Post = 'post';
    case Tutorial = 'tutorial';
    case Review = 'review';
    case News = 'news';
}
