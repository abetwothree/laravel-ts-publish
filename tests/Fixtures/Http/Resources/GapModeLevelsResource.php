<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\GapModeHeldValue;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\GapModeValueSource;
use Illuminate\Http\Resources\Json\JsonResource;

/** Methods whose keys land beside a key their `@return` types without a key of their own, each read by a helper. */
final class GapModeLevelsResource extends JsonResource
{
    /**
     * A spread helper's keys beside a typed key.
     *
     * @return array{k: string}
     */
    public function spreadsHelper(): array
    {
        return ['k' => 'k', ...$this->spreadPart()];
    }

    /**
     * A helper's keys returned whole.
     *
     * @return array{k: string}
     */
    public function returnsHelper(): array
    {
        return $this->returnedPart();
    }

    /**
     * A returned local written whole by a helper.
     *
     * @return array{k: string}
     */
    public function writesLocal(): array
    {
        $data = $this->writtenPart();

        return $data;
    }

    /**
     * A typed key written from an untyped key of the same local.
     *
     * @return array{k: string}
     */
    public function aliasesWalk(): array
    {
        $data = [];
        $data['raw'] = $this->aliasRead();
        $data['k'] = $data['raw'];

        return $data;
    }

    /**
     * An untyped key written beside a typed key that never reads the local.
     *
     * @return array{k: string}
     */
    public function writesBesideKey(): array
    {
        $data = [];
        $data['raw'] = $this->besideRead();
        $data['k'] = 'k';

        return $data;
    }

    /**
     * A `merge()` literal's untyped key beside a typed key.
     *
     * @return array{k: string}
     */
    public function mergesLiteral(): array
    {
        return ['k' => 'k', $this->merge(['m' => $this->mergedRead()])];
    }

    /**
     * A `merge()` literal's own typed key.
     *
     * @return array{m: string}
     */
    public function mergesTypedKey(): array
    {
        return [$this->merge(['m' => $this->mergedTypedRead()])];
    }

    /**
     * A `merge()` closure whose parameter holds its default.
     *
     * @return array{k: string}
     */
    public function mergesClosureDefault(): array
    {
        return ['k' => 'k', $this->merge(fn (GapModeHeldValue $held = new GapModeHeldValue) => ['d' => $held])];
    }

    /**
     * A helper argument read before the helper's body, beside a typed key.
     *
     * @return array{k: string}
     */
    public function passesArgument(): array
    {
        return ['k' => 'k', ...$this->takesValue($this->argumentRead())];
    }

    /**
     * A documented helper's argument, under no docblock of its own.
     */
    public function passesDocumentedArgument(): array // @phpstan-ignore missingType.iterableValue
    {
        return [...$this->takesTypedValue($this->documentedArgumentRead())];
    }

    /**
     * An undocumented helper's argument, under no docblock of its own.
     */
    public function passesPlainArgument(): array // @phpstan-ignore missingType.iterableValue
    {
        return [...$this->takesValue($this->plainArgumentRead())];
    }

    /**
     * A value's named keys spread beside a typed key.
     *
     * @return array{k: string}
     */
    public function spreadsValue(): array
    {
        return ['k' => 'k', ...new GapModeValueSource()->pairs()];
    }

    /**
     * A value's map spread beside a typed key.
     *
     * @return array{k: string}
     */
    public function spreadsMap(): array
    {
        return ['k' => 'k', ...new GapModeValueSource()->counts()];
    }

    /**
     * A spread helper's keys.
     *
     * @return array<string, mixed>
     */
    private function spreadPart(): array
    {
        return ['p' => $this->spreadRead()];
    }

    /**
     * A whole return's keys.
     *
     * @return array<string, mixed>
     */
    private function returnedPart(): array
    {
        return ['k' => 'k', 'w' => $this->returnedRead()];
    }

    /**
     * A whole write's keys.
     *
     * @return array<string, mixed>
     */
    private function writtenPart(): array
    {
        return ['k' => 'k', 'l' => $this->writtenRead()];
    }

    /**
     * An undocumented helper's keys, from its argument.
     *
     * @return array<string, mixed>
     */
    private function takesValue(mixed $value): array
    {
        return ['t' => $value];
    }

    /**
     * A documented helper's keys, from its argument.
     *
     * @return array{t: string}
     */
    private function takesTypedValue(mixed $value): array
    {
        return ['t' => $value];
    }

    /**
     * A spread helper's value.
     */
    private function spreadRead(): string
    {
        return 'p';
    }

    /**
     * A whole return's value.
     */
    private function returnedRead(): string
    {
        return 'w';
    }

    /**
     * A whole write's value.
     */
    private function writtenRead(): string
    {
        return 'l';
    }

    /**
     * An aliased key's value.
     */
    private function aliasRead(): string
    {
        return 'raw';
    }

    /**
     * An untyped key's value beside a typed key.
     */
    private function besideRead(): string
    {
        return 'raw';
    }

    /**
     * A merged untyped key's value.
     */
    private function mergedRead(): string
    {
        return 'm';
    }

    /**
     * A merged typed key's value.
     */
    private function mergedTypedRead(): string
    {
        return 'm';
    }

    /**
     * An argument's value beside a typed key.
     */
    private function argumentRead(): string
    {
        return 't';
    }

    /**
     * A documented helper's argument value.
     */
    private function documentedArgumentRead(): string
    {
        return 't';
    }

    /**
     * An undocumented helper's argument value.
     */
    private function plainArgumentRead(): string
    {
        return 't';
    }
}
