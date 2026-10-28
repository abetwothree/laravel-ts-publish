<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui;

use JsonSerializable;
use Workbench\App\Packages\Ui\Board;

/**
 * A JsonSerializable whose docblock shape names a value object that keeps its members' overrides.
 */
final class BoardJson implements JsonSerializable
{
    /**
     * The board and its title.
     *
     * @return array{board: Board, title: string}
     */
    public function jsonSerialize(): array
    {
        return ['board' => new Board, 'title' => 'Board'];
    }
}
