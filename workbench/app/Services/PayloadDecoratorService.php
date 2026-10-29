<?php

declare(strict_types=1);

namespace Workbench\App\Services;

/**
 * Methods that add keys to the array they are passed and return it, under vague declarations, so the body is the only
 * place the shape is written and the caller's own keys never appear in it.
 */
final class PayloadDecoratorService
{
    /**
     * Adds one key, called statically.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function decorateStatic(array $data): array
    {
        $data['seen'] = true;

        return $data;
    }

    /**
     * Adds one key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function decorate(array $data): array
    {
        $data['seen'] = true;

        return $data;
    }

    /**
     * Adds one key when the flag is on.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function decorateWhen(array $data, bool $flag): array
    {
        if ($flag) {
            $data['flag'] = 'on';
        }

        return $data;
    }

    /**
     * Fills a key the caller may have set, drops another, and adds one.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function fillDefaults(array $data): array
    {
        $data['href'] ??= '/';
        unset($data['secret']);
        $data['seen'] = true;

        return $data;
    }

    /**
     * Replaces the array before adding a key, so none of the caller's keys reach the return.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function rebuilt(array $data): array
    {
        $data = ['id' => 1];
        $data['seen'] = true;

        return $data;
    }

    /**
     * Returns the array untouched.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function passThrough(array $data): array
    {
        return $data;
    }

    /**
     * Adds a key the caller's array keeps if it already holds one.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function merged(array $data): array
    {
        $data += ['seen' => true];

        return $data;
    }

    /**
     * Adds one key under a precise declaration, which is read before the body.
     *
     * @param  array<string, mixed>  $data
     * @return array{seen: bool}
     */
    public function declared(array $data): array
    {
        $data['seen'] = true;

        return $data;
    }

    /** Adds one key to a parameter with no declared type. */
    public function untyped($data)
    {
        $data['seen'] = true;

        return $data;
    }
}
