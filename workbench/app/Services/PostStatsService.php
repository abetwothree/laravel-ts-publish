<?php

declare(strict_types=1);

namespace Workbench\App\Services;

class PostStatsService
{
    /**
     * @return array{views: int, likes: int}
     */
    public function summary(): array
    {
        return ['views' => 0, 'likes' => 0];
    }
}
