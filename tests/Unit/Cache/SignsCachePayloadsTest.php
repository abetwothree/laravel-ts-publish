<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Cache\Concerns\SignsCachePayloads;

beforeEach(function () {
    $this->host = new class
    {
        use SignsCachePayloads;

        /** @param array<string, mixed> $value */
        public function sign(array $value, ?string $key): string
        {
            return $this->signPayload($value, $key);
        }

        /** @return array<string, mixed>|null */
        public function read(string $content, ?string $key): ?array
        {
            return $this->readSignedPayload($content, $key);
        }
    };
});

it('round-trips a string-keyed array when signed with a key', function () {
    $signed = $this->host->sign(['snapshot' => 'data', 'n' => 1], 'secret');

    expect($signed)->toContain(':')
        ->and($this->host->read($signed, 'secret'))->toBe(['snapshot' => 'data', 'n' => 1]);
});

it('returns the payload unchanged when no signing key is configured', function () {
    $signed = $this->host->sign(['n' => 1], null);

    expect($this->host->read($signed, null))->toBe(['n' => 1]);
});

it('rejects a payload whose HMAC signature does not match', function () {
    expect($this->host->read('deadbeef:'.serialize(['n' => 1]), 'secret'))->toBeNull();
});

it('rejects a signed payload read with the wrong key', function () {
    $signed = $this->host->sign(['n' => 1], 'secret');

    expect($this->host->read($signed, 'other-key'))->toBeNull();
});

it('never instantiates objects from a payload', function () {
    // A serialized array whose value is a real object. With allowed_classes:false
    // the object must NOT be rebuilt, so no __wakeup()/__destruct() gadget runs.
    // DateTimeImmutable (rather than an exception) keeps this portable: an
    // exception constructed inside Pest's test closure captures un-serializable
    // closures in its trace when zend.exception_ignore_args is off.
    $payload = serialize(['evil' => new DateTimeImmutable('2020-01-01')]);

    $result = $this->host->read($payload, null);

    expect($result)->toBeArray()
        ->and($result['evil'])->not->toBeInstanceOf(DateTimeImmutable::class);
});
