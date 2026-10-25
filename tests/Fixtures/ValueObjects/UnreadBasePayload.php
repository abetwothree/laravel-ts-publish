<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Illuminate\Http\Request;

/**
 * Helpers whose returned array holds keys no analysis reads: the request's input as a base, a `+=` or a spread, an
 * array returned untouched, and keys a loop, an append or a nested write adds.
 */
final class UnreadBasePayload
{
    public string $raw = '{"k":1}';

    public ?array $extra = null;

    public array|string $either = 'none';

    public function __construct(private Request $request) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'unread_local' => $this->unreadLocal(),
            'untouched' => $this->untouched(json_decode($this->raw, true)),
        ];
    }

    /** @return array<string, mixed> */
    public function values(): array
    {
        return [
            'extended' => $this->extended(['id' => 1], ['k' => true]),
            'topped_up' => $this->toppedUp(),
            'appended' => $this->appended(['id' => 1]),
            'nested_key' => $this->nestedKey(['id' => 1]),
            'request_untouched' => $this->untouched($this->request->all()),
            'maybe_untouched' => $this->maybeUntouched($this->extra),
            'raw_base' => $this->rawBase(),
            'raw_spread' => $this->rawSpread(),
            'either_untouched' => $this->eitherUntouched($this->either),
            'null_base' => $this->nullBase(),
        ];
    }

    /**
     * Adds a key to the request's input.
     *
     * @return array<string, mixed>
     */
    private function unreadLocal(): array
    {
        $data = $this->request->all();
        $data['x'] = 1;

        return $data;
    }

    /**
     * Returns the array it is passed.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function untouched(array $data): array
    {
        return $data;
    }

    /**
     * Copies each extra key into the array it is passed, then adds one of its own.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function extended(array $data, array $extra): array
    {
        foreach ($extra as $key => $value) {
            $data[$key] = $value;
        }

        $data['seen'] = true;

        return $data;
    }

    /**
     * Adds the request's input beside a key of its own.
     *
     * @return array<string, mixed>
     */
    private function toppedUp(): array
    {
        $data = ['x' => 1];
        $data += $this->request->all();

        return $data;
    }

    /**
     * Appends a value to the array it is passed.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function appended(array $data): array
    {
        $data[] = 5;

        return $data;
    }

    /**
     * Writes a key below a key of the array it is passed.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function nestedKey(array $data): array
    {
        $data['a']['b'] = 1;

        return $data;
    }

    /**
     * Returns the array it is passed, or null.
     *
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>|null
     */
    private function maybeUntouched(?array $data): ?array
    {
        return $data;
    }

    /** The request's input, under a signature that says nothing of its keys. */
    private function rawInput(): array
    {
        return $this->request->all();
    }

    /**
     * Adds a key to the array a helper returns.
     *
     * @return array<string, mixed>
     */
    private function rawBase(): array
    {
        $data = $this->rawInput();
        $data['x'] = 1;

        return $data;
    }

    /**
     * Spreads the array a helper returns beside a key of its own.
     *
     * @return array<string, mixed>
     */
    private function rawSpread(): array
    {
        $data = [...$this->rawInput(), 'x' => 1];

        return $data;
    }

    /**
     * Returns the array or text it is passed.
     *
     * @param  array<string, mixed>|string  $data
     * @return array<string, mixed>|string
     */
    private function eitherUntouched(array|string $data): array|string
    {
        return $data;
    }

    /** Nothing, which holds no key. */
    private function nothing(): ?array
    {
        return null;
    }

    /**
     * Adds a key where a helper returns nothing.
     *
     * @return array<string, mixed>
     */
    private function nullBase(): array
    {
        $data = $this->nothing();
        $data['x'] = 1;

        return $data;
    }
}
