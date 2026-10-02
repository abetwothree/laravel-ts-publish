<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\ClassTokenQueue;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\Crm\Http\Resources\UserResource as CrmUserResource;
use Workbench\Crm\Models\User as CrmUser;

/**
 * What the alias pass writes for a type, rebuilt from the classes a queue handed out: a whole word that names a queued
 * class, with no dot or identifier character touching it, becomes the alias of the next class taken. Reading words, not
 * the pattern the alias pass uses, is what makes the two walks comparable.
 *
 * @param  list<string>  $fqcns  the queue
 * @param  list<string>  $taken  what take() handed out
 * @param  array<string, string>  $names  FQCN => name
 * @param  array<string, string>  $aliases  FQCN => alias, for the subset that was aliased
 * @return array{string, int} the rebuilt type, and how many tokens it read
 */
function aliasFromTakenClasses(string $type, array $fqcns, array $taken, array $names, array $aliases): array
{
    $queued = array_map(static fn (string $fqcn): string => $names[$fqcn], $fqcns);
    $read = 0;

    $rebuilt = preg_replace_callback('/(?<![\w$.])[\w$]+/', static function (array $word) use ($queued, $taken, $names, $aliases, &$read): string {
        if (! in_array($word[0], $queued, true)) {
            return $word[0];
        }

        // A queue that handed out too few leaves the word as it is, so the count differs and the comparison says so.
        $fqcn = $taken[$read++] ?? null;

        return $fqcn === null ? $word[0] : ($aliases[$fqcn] ?? $names[$fqcn]);
    }, $type);

    return [(string) $rebuilt, $read];
}

dataset('queued types', [
    'a nullable arm' => ['User | null', ['App\\Models\\User']],
    'an array' => ['User[]', ['Crm\\Models\\User']],
    'a generic' => ["Pick<User, 'id'> | Record<string, User>", ['App\\Models\\User', 'Crm\\Models\\User']],
    'an arm that is itself a union' => ['(User | Post)[] | null', ['App\\Models\\User', 'App\\Models\\Post']],
    'an inline object' => ['{ owner: User; post: Post; editor: User }', ['Crm\\Models\\User', 'App\\Models\\Post', 'App\\Models\\User']],
    'a name that is a prefix of another' => [
        'UserResourceCollection | UserResource | User',
        ['App\\Models\\User', 'Crm\\Http\\Resources\\UserResource', 'App\\Http\\Resources\\UserResourceCollection'],
    ],
    'more occurrences than classes' => ['User | User[] | Record<string, User>', ['App\\Models\\User']],
    'a queue that runs short of the type' => ['User | User | User', ['App\\Models\\User', 'Crm\\Models\\User']],
    'a name after a dot' => ['crm.models.User | User', ['Crm\\Models\\User']],
    'a quoted literal that spells the name' => ["Pick<User, 'User'> | User", ['App\\Models\\User', 'Crm\\Models\\User']],
    'a name used as an object key' => ['{ User: User } | User', ['App\\Models\\User', 'Crm\\Models\\User']],
    'a name that ends a longer word' => ['AdminUser | User', ['App\\Models\\User', 'Crm\\Models\\User']],
]);

it('hands each occurrence of a name its own class, in order, across the pieces it is asked about', function () {
    $queue = new ClassTokenQueue([User::class, CrmUser::class, Post::class], class_basename(...));

    expect($queue->take('User | null'))->toBe([User::class])
        ->and($queue->take('{ owner: User; post: Post }'))->toBe([CrmUser::class, Post::class]);
});

it('lets the last class of a name cover every further occurrence', function () {
    $queue = new ClassTokenQueue([User::class], class_basename(...));

    expect($queue->take("Pick<User, 'id'> | User[]"))->toBe([User::class, User::class])
        ->and($queue->take('User'))->toBe([User::class]);
});

it('reads a name only as a whole token, never inside a longer name or after a dot', function () {
    $queue = new ClassTokenQueue([User::class], class_basename(...));

    expect($queue->take('UserResource | crm.models.User | AdminUser'))->toBe([])
        ->and($queue->take('User'))->toBe([User::class]);
});

it('takes nothing from a text that names none of its classes, or with no classes at all', function () {
    expect((new ClassTokenQueue([User::class], class_basename(...)))->take('string | null'))->toBe([])
        ->and((new ClassTokenQueue([], class_basename(...)))->take('User'))->toBe([]);
});

it('hands out the class the alias pass spells at each token, however the token is wrapped', function (string $type, array $fqcns) {
    $names = [
        'App\\Models\\User' => 'User',
        'Crm\\Models\\User' => 'User',
        'App\\Models\\Post' => 'Post',
        'App\\Http\\Resources\\UserResource' => 'UserResource',
        'Crm\\Http\\Resources\\UserResource' => 'UserResource',
        'App\\Http\\Resources\\UserResourceCollection' => 'UserResourceCollection',
    ];
    // Post and UserResourceCollection stay unaliased, which the alias pass spells as their own name.
    $aliases = [
        'App\\Models\\User' => 'AppUser',
        'Crm\\Models\\User' => 'CrmUser',
        'App\\Http\\Resources\\UserResource' => 'AppUserResource',
        'Crm\\Http\\Resources\\UserResource' => 'CrmUserResource',
    ];

    $taken = (new ClassTokenQueue($fqcns, static fn (string $fqcn): string => $names[$fqcn]))->take($type);
    $spelled = TsTypeString::aliasPropertyType($type, $fqcns, $names, $aliases);

    expect($spelled)->not->toBe($type)
        ->and(aliasFromTakenClasses($type, $fqcns, $taken, $names, $aliases))->toBe([$spelled, count($taken)]);
})->with('queued types');

it('hands out the same classes a piece at a time as it does for the whole type', function (string $type, array $fqcns) {
    $whole = (new ClassTokenQueue($fqcns, class_basename(...)))->take($type);
    $pieces = new ClassTokenQueue($fqcns, class_basename(...));
    $byPiece = array_merge(...array_map($pieces->take(...), TsTypeString::splitTopLevelUnion($type)));

    expect($byPiece)->toBe($whole);
})->with('queued types');

it('outruns its tokens only where a name they read is queued for two classes, and more often than they read it', function (array $fqcns, string $type, bool $outruns) {
    $queue = new ClassTokenQueue($fqcns, class_basename(...));
    $queue->take($type);

    expect($queue->outrunsItsTokens())->toBe($outruns);
})->with([
    'a queue that lines up with its tokens' => [[User::class, CrmUser::class], 'User | User | null', false],
    'one token with two classes behind it' => [[User::class, CrmUser::class], 'User[]', true],
    'a merged arm: three entries, two tokens, two classes' => [[User::class, CrmUser::class, CrmUser::class], 'User[] | User | null', true],
    'one class queued twice behind one token' => [[User::class, User::class], 'User[]', false],
    'two classes of a name no token read' => [[User::class, CrmUser::class], 'number', false],
    'fewer entries than tokens' => [[User::class, CrmUser::class], '{ a: User; b: User; c: User }', false],
    'a name that lines up, then one that outruns its tokens' => [[Post::class, User::class, CrmUser::class], 'Post | User[]', true],
    'a name that outruns its tokens, then one that lines up' => [[User::class, CrmUser::class, Post::class], 'User[] | Post', true],
]);

it('outruns its tokens just as well when the resource naming closure names its classes', function () {
    $queue = new ClassTokenQueue(
        [UserResource::class, CrmUserResource::class],
        static fn (string $fqcn): string => TsNaming::resourceTypeName($fqcn),
    );
    $queue->take('UserResource | null');

    expect($queue->outrunsItsTokens())->toBeTrue();
});

it('answers with the class behind each token when the info carries that queue, else each class once', function (array $info, array $queue) {
    expect(ClassTokenQueue::fqcnsOf($info))->toBe($queue);
})->with([
    'an info with a queue per token' => [
        ['classFqcns' => [User::class, CrmUser::class], 'classTokenFqcns' => [User::class, User::class, CrmUser::class]],
        [User::class, User::class, CrmUser::class],
    ],
    'an info without one' => [
        ['classFqcns' => [User::class, CrmUser::class]],
        [User::class, CrmUser::class],
    ],
    'an info that names no class' => [
        ['classFqcns' => []],
        [],
    ],
]);
