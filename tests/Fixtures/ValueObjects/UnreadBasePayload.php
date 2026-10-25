<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Illuminate\Http\Request;

/**
 * Helpers whose returned array holds keys no analysis reads: the request's input as a base or a `+=`, a decoded array
 * returned untouched, and keys a loop copies in.
 */
final class UnreadBasePayload
{
    public string $raw = '{"k":1}';

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
}
