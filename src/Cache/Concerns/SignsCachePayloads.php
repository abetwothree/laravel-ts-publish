<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Cache\Concerns;

use Throwable;

trait SignsCachePayloads
{
    /**
     * Serialize a cache payload, prepending an HMAC-SHA256 signature when a
     * signing key is configured. The result is an opaque string suitable for
     * writing to disk or a Laravel cache store.
     *
     * @param  array<string, mixed>  $value
     */
    protected function signPayload(array $value, ?string $key): string
    {
        $serialized = serialize($value);

        if ($key !== null && $key !== '') {
            return hash_hmac('sha256', $serialized, $key).':'.$serialized;
        }

        return $serialized;
    }

    /**
     * Verify a payload's HMAC (when a key is configured) and unserialize it with
     * objects disabled, returning the plain string-keyed array — or null when the
     * signature is missing/invalid, the payload is corrupt, or it is not a
     * string-keyed array. Never instantiates objects, closing the
     * object-injection surface on untrusted cache backends.
     *
     * @return array<string, mixed>|null
     */
    protected function readSignedPayload(string $content, ?string $key): ?array
    {
        $serialized = $this->verifySignature($content, $key);

        if ($serialized === null) {
            return null;
        }

        try {
            $data = unserialize($serialized, ['allowed_classes' => false]);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($data)) {
            return null;
        }

        $typed = [];

        foreach ($data as $k => $v) {
            if (! is_string($k)) {
                return null;
            }

            $typed[$k] = $v;
        }

        return $typed;
    }

    /**
     * Verify and strip the HMAC signature, returning the raw serialized payload,
     * or null when the signature is missing or does not match. Unsigned payloads
     * (no configured key) are returned as-is.
     */
    private function verifySignature(string $content, ?string $key): ?string
    {
        if ($key === null || $key === '') {
            return $content;
        }

        if (! str_contains($content, ':')) {
            return null;
        }

        [$signature, $serialized] = explode(':', $content, 2);

        if (! hash_equals($signature, hash_hmac('sha256', $serialized, $key))) {
            return null;
        }

        return $serialized;
    }
}
