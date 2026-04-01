<?php

declare(strict_types=1);

namespace Workbench\Blog\Enums;

enum ArticleStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Published = 'published';
    case Archived = 'archived';
}
