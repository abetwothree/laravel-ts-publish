<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\DeclaringFile;
use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\PropertyDocblockTypeReader;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\Concerns\WrapsPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\Parents\PostWrappingResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PostWrappingChildResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\TraitWrappedPostResource;
use Illuminate\Http\Resources\Json\JsonResource;

// PHP 8.4 reflects a trait's redeclaration of a parent's property as the parent's; each test hands that property over.
describe('a trait redeclaring a parent\'s property', function () {
    test('names the trait as the file that declares it', function () {
        $property = new ReflectionProperty(JsonResource::class, 'resource');
        $subject = new ReflectionClass(TraitWrappedPostResource::class);

        expect(DeclaringFile::redeclaringTrait($property, $subject)?->getName())->toBe(WrapsPost::class)
            ->and(DeclaringFile::ofProperty($property, $subject)->getName())->toBe(WrapsPost::class)
            ->and(DeclaringFile::ofProperty($property)->getName())->toBe(JsonResource::class);
    });

    test('is not found where no trait between the subject and the declaring class redeclares it', function (string $declaring, string $subject) {
        $property = new ReflectionProperty($declaring, 'resource');

        expect(DeclaringFile::redeclaringTrait($property, new ReflectionClass($subject)))->toBeNull();
    })->with([
        'a parent\'s own redeclaration' => [PostWrappingResource::class, PostWrappingChildResource::class],
        'the subject is the declaring class' => [JsonResource::class, JsonResource::class],
    ]);

    test('takes its docblock from the trait\'s source', function () {
        $property = new ReflectionProperty(JsonResource::class, 'resource');
        $tag = resolve(PropertyDocblockTypeReader::class)->tag($property, new ReflectionClass(TraitWrappedPostResource::class));

        expect(DeclaringFile::propertyDocComment(new ReflectionClass(WrapsPost::class), 'resource'))->toBe('/** @var Post */')
            ->and(DeclaringFile::propertyDocComment(new ReflectionClass(WrapsPost::class), 'missing'))->toBeFalse()
            ->and($tag)->not->toBeNull()
            ->and($tag['type'] ?? null)->toBe('Post')
            ->and(isset($tag['context']) ? $tag['context']->getName() : null)->toBe(WrapsPost::class);
    });
});
