<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;

/**
 * A model on the `posts` table whose getters' bodies type nothing, typed by the `@return` docblock written before the
 * getter closure, and methods whose vague `: array` return reads them without imports.
 */
final class DocumentedGetterPost extends Model
{
    protected $table = 'posts';

    /**
     * Each documented getter beside a typed column.
     *
     * @return array<string, mixed>
     */
    public function readDocumented(): array
    {
        return ['label' => $this->doc_label, 'post' => $this->doc_post, 'status' => $this->doc_status, 'id' => $this->id];
    }

    /**
     * Each documented getter through a literal-key filter.
     *
     * @return array<string, mixed>
     */
    public function readPicked(): array
    {
        return ['v' => $this->only(['id', 'doc_count', 'doc_post', 'doc_status']), 'id' => $this->id];
    }

    /** A getter passed as a named argument. */
    protected function docLabel(): Attribute
    {
        return Attribute::make(get: /** @return string */ fn ($v) => json_decode($v));
    }

    /** A getter passed positionally. */
    protected function docCount(): Attribute
    {
        return Attribute::get(/** @return int */ fn () => json_decode('1'));
    }

    /** A getter documented as a model. */
    protected function docPost(): Attribute
    {
        return Attribute::get(/** @return Post|null */ fn () => json_decode('null'));
    }

    /** A getter documented as an enum. */
    protected function docStatus(): Attribute
    {
        return Attribute::get(/** @return Status */ fn () => json_decode('0'));
    }
}
