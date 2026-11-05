<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverMemberReader;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\App\ValueObjects\ArrayableData;
use Workbench\App\ValueObjects\PostStats;

/** The published type of `<receiver>->name`, or null where the reader declines. */
function memberReaderType(ReceiverType $held, string $name): ?string
{
    return ReceiverMemberReader::value($held, $name, new AnalysisScope(new ReflectionClass(Post::class), Post::class))['type'] ?? null;
}

describe('value()', function () {
    test('reads a model attribute before its relation, as Model::__get() does', function (string $name, string $type) {
        expect(memberReaderType(ReceiverType::of(Post::class), $name))->toBe($type);
    })->with([
        'a column' => ['title', 'string'],
        'an accessor' => ['title_display', 'string | null'],
        'an enum cast' => ['status', 'StatusType'],
        'a relation to one' => ['author', 'User'],
        'a relation to many' => ['comments', 'Comment[]'],
    ]);

    test('carries the related model of a relation, so its token keeps its import', function () {
        expect(ReceiverMemberReader::value(ReceiverType::of(Post::class), 'author', new AnalysisScope(new ReflectionClass(Post::class)))['modelFqcn'] ?? null)
            ->toBe(User::class);
    });

    test('reads a column a model with no attributes holds as null, unless a write filled it', function () {
        expect(memberReaderType(ReceiverType::of(Post::class)->asAttributeless(), 'title'))->toBe('string | null')
            ->and(memberReaderType(ReceiverType::of(Post::class)->asAttributeless(['title']), 'title'))->toBe('string')
            ->and(memberReaderType(ReceiverType::of(Post::class)->asAttributeless(), 'author'))->toBe('User');
    });

    test('unions every class the receiver holds, and declines when one cannot type the member', function () {
        expect(memberReaderType(new ReceiverType([Post::class, Comment::class]), 'content'))->toBe('string')
            ->and(memberReaderType(new ReceiverType([Post::class, User::class]), 'title'))->toBeNull();
    });

    test('reads an enum member and a public property of any other class', function (string $class, string $name, ?string $type) {
        expect(memberReaderType(ReceiverType::of($class), $name))->toBe($type);
    })->with([
        'a backed enum value' => [Status::class, 'value', 'StatusType'],
        'a backed enum name' => [Status::class, 'name', 'StatusKind'],
        'a public property' => [PostStats::class, 'views', 'number'],
        'a property the class lacks' => [PostStats::class, 'missing', null],
    ]);

    test('declines a name the model neither lists nor relates', function () {
        expect(memberReaderType(ReceiverType::of(Post::class), 'nope'))->toBeNull();
    });
});

describe('classes()', function () {
    test('names what a model member holds: the related model, or a collection carrying it', function () {
        expect(ReceiverMemberReader::classes(Post::class, 'author')?->classes)->toBe([User::class])
            ->and(ReceiverMemberReader::classes(Post::class, 'comments')?->classes)->toBe([EloquentCollection::class])
            ->and(ReceiverMemberReader::classes(Post::class, 'comments')?->elementModel)->toBe(Comment::class)
            ->and(ReceiverMemberReader::classes(Post::class, 'title'))->toBeNull();
    });

    test('names the class a public property declares, and nothing for a scalar one', function () {
        expect(ReceiverMemberReader::classes(ArrayableData::class, 'recordedAt')?->classes)->toBe([Carbon::class])
            ->and(ReceiverMemberReader::classes(PostStats::class, 'views'))->toBeNull();
    });

    test('names a relation alone through relationClasses()', function () {
        expect(ReceiverMemberReader::relationClasses(Post::class, 'author')?->classes)->toBe([User::class])
            ->and(ReceiverMemberReader::relationClasses(Post::class, 'title'))->toBeNull();
    });
});
