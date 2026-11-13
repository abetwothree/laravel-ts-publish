<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Keys a `@return` types, leaves vague or leaves out, each value read through a helper of its own. */
final class GapModeKeysResource extends JsonResource
{
    /**
     * A typed key beside a vague one and one the docblock leaves out.
     *
     * @return array{typed: string, vague: mixed}
     */
    public function toArray(Request $request): array
    {
        return ['typed' => $this->typedRead(), 'vague' => $this->vagueRead(), 'untyped' => $this->untypedRead()];
    }

    /**
     * Every key typed by an informative record, an index signature beside them.
     *
     * @return array<string, string>
     */
    public function typedRecord(Request $request): array
    {
        $name = (string) $request->input('name');

        return ['named' => $this->recordRead(), "{$name}_tag" => $this->signatureRead()];
    }

    /**
     * Every key left vague by a record of `mixed`.
     *
     * @return array<string, mixed>
     */
    public function mixedRecord(): array
    {
        return ['named' => $this->mixedRead()];
    }

    /**
     * Keys spelled as PHP reads a `key?:` and a `0:` member.
     *
     * @return array{rows?: list<int>, 0: string}
     */
    public function spelledKeys(): array
    {
        return ['rows' => $this->rowsRead(), 0 => $this->zeroRead()];
    }

    /**
     * A typed key's value.
     */
    private function typedRead(): string
    {
        return 'typed';
    }

    /**
     * A vague key's value.
     */
    private function vagueRead(): string
    {
        return 'vague';
    }

    /**
     * An untyped key's value.
     */
    private function untypedRead(): string
    {
        return 'untyped';
    }

    /**
     * A record key's value.
     */
    private function recordRead(): string
    {
        return 'named';
    }

    /**
     * An index signature's value.
     */
    private function signatureRead(): string
    {
        return 'tag';
    }

    /**
     * A `mixed` record key's value.
     */
    private function mixedRead(): string
    {
        return 'mixed';
    }

    /**
     * An optional member's value.
     *
     * @return list<int>
     */
    private function rowsRead(): array
    {
        return [1];
    }

    /**
     * An int-keyed member's value.
     */
    private function zeroRead(): string
    {
        return 'zero';
    }
}
