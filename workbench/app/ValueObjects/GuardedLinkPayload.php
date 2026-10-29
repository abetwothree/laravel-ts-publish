<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

/**
 * Fixture: helpers that write keys into the array they are passed and return it, so each result is the argument's
 * shape with the helper's writes joined in.
 */
final class GuardedLinkPayload
{
    public bool $flag = false;

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = ['id' => 1, 'name' => 'x', 'url' => 'https://example.com', 'disabled' => false];

        return $this->guard($data);
    }

    /** @return array<string, mixed> */
    public function values(): array
    {
        return [
            'nested' => $this->guard(['id' => 1, 'url' => 'u', 'disabled' => false]),
            'seen' => $this->seen(['id' => 1]),
            'declared' => $this->declared(['id' => 1, 'name' => 'x']),
            'opaque' => $this->guard($this->opaque()),
            'rebuilt' => $this->rebuilt(['id' => 1]),
        ];
    }

    /**
     * Clears the link when the flag is on.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function guard(array $data): array
    {
        if ($this->flag) {
            $data['url'] = null;
            $data['disabled'] = true;
        }

        return $data;
    }

    /**
     * Adds a key the caller's array keeps if it already holds one.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function seen(array $data): array
    {
        $data += ['seen' => true];

        return $data;
    }

    /**
     * Adds one key to a parameter whose shape is declared.
     *
     * @param  array{id: int, name: string}  $data
     * @return array<string, mixed>
     */
    private function declared(array $data): array
    {
        $data['extra'] = true;

        return $data;
    }

    /** An array nothing types. */
    private function opaque(): mixed
    {
        return json_decode('{}', true);
    }

    /**
     * Replaces the array before adding a key, so none of the caller's keys reach the return.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function rebuilt(array $data): array
    {
        $data = ['fresh' => 1];
        $data['more'] = true;

        return $data;
    }
}
