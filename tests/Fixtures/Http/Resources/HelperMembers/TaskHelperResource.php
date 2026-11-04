<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HelperMembers;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\HelperTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A task's relation and method reads, in place on a local and in a helper taking the model as a typed parameter. */
final class TaskHelperResource extends JsonResource
{
    /** @var HelperTask */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $task = $this->resource;

        return [
            'in_rel' => [
                'assignee' => $task->assignee,
                'fresh' => $task->fresh(),
                'team' => $task->team,
                'team_name' => $task->team->name,
                'key' => $task->getKey(),
            ],
            'helper_rel' => $this->relations($this->resource),
        ];
    }

    /** The task's relations, a fresh copy, its team's name and its key. */
    private function relations(HelperTask $task): array
    {
        return [
            'assignee' => $task->assignee,
            'fresh' => $task->fresh(),
            'team' => $task->team,
            'team_name' => $task->team->name,
            'key' => $task->getKey(),
        ];
    }
}
