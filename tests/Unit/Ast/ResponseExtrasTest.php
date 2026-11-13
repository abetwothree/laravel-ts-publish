<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Resources\ResponseExtras;

/**
 * The type a response of this type sends once an `additional()` call adds these keys, its body under a key.
 */
function responseExtrasApplied(string $added, string $response, ?string $bodyKey): string
{
    return ResponseExtras::ofValue(['type' => $added, 'optional' => false])
        ->appliedTo(['type' => $response, 'optional' => false], $bodyKey)['type'];
}

// A key matches by the name its spelling decodes to, so two spellings of one key are the one key Laravel merges.
test('an added key meets a response key or the body key by the key it names, however it is quoted', function (string $added, string $response, ?string $bodyKey, string $sent) {
    expect(responseExtrasApplied($added, $response, $bodyKey))->toBe($sent);
})->with([
    'the body key, escaped' => ['{ "a\/b": number }', 'UserCardResource', 'a/b', "Omit<UserCardResource, 'a/b'> & { \"a\\/b\": unknown }"],
    'the body key, as a unicode escape' => ['{ "\u00e9": number }', 'UserCardResource', 'é', "Omit<UserCardResource, 'é'> & { \"\\u00e9\": unknown }"],
    'another key beside the body key' => ['{ "a\/b": number }', 'UserCardResource', 'data', 'UserCardResource & { "a\/b": number }'],
    'a response key quoted the other way' => ['{ "a\/b": { x: number } }', "{ 'a/b': { y: string } }", null, '{ "a\/b": { y: string; x: number } }'],
    'a response key with an escaped quote' => ["{ 'it\\'s': number }", '{ "it\'s": string }', null, "{ 'it\\'s': unknown }"],
    'a bare decimal key, optional on one side' => ['{ 1.5?: number }', '{ 1.5: string }', null, '{ 1.5: unknown }'],
]);

test('reads added keys past a gap arm beside them and their open marker', function () {
    expect(responseExtrasApplied('{ a: number } | $gap1', 'UserCardResource', 'data'))
        ->toBe(responseExtrasApplied('{ a: number }', 'UserCardResource', 'data'))
        ->and(ResponseExtras::ofValue(['type' => 'never[] | $gap1', 'optional' => false])->object)->toBeNull()
        ->and(ResponseExtras::ofValue(['type' => '{ a?: number; #$open: unknown }', 'optional' => false])->sent)->toBeNull();
});
