<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Members\DependencyFiles;
use AbeTwoThree\LaravelTsPublish\Ast\Members\MethodReturnTypeResolver;
use Illuminate\Support\Str;
use Workbench\App\Models\Post;

test('the running application\'s dependencies are the files under Composer\'s vendor directory and this package\'s src', function () {
    $dependencies = DependencyFiles::ofApplication();

    expect($dependencies->contains((string) new ReflectionClass(Str::class)->getFileName()))->toBeTrue()
        ->and($dependencies->contains((string) new ReflectionClass(MethodReturnTypeResolver::class)->getFileName()))->toBeTrue()
        ->and($dependencies->contains((string) new ReflectionClass(Post::class)->getFileName()))->toBeFalse()
        ->and($dependencies->contains(__FILE__))->toBeFalse()
        ->and($dependencies->contains(false))->toBeTrue();
});

test('a dependency is a file under a vendor directory Composer registered, not under any folder named vendor', function (string $file, bool $expected) {
    expect(DependencyFiles::withVendorDirectories(['/home/vendor/shop/vendor'])->contains($file))->toBe($expected);
})->with([
    'the application\'s own class, under a vendor folder' => ['/home/vendor/shop/app/Models/Post.php', false],
    'a dependency in the registered directory' => ['/home/vendor/shop/vendor/laravel/framework/src/Model.php', true],
    'a sibling directory sharing the prefix' => ['/home/vendor/shop/vendor-legacy/Old.php', false],
]);

test('a custom vendor-dir counts, and a folder named vendor outside it does not', function () {
    $dependencies = DependencyFiles::withVendorDirectories(['/srv/shop/deps/']);

    expect($dependencies->contains('/srv/shop/deps/laravel/framework/src/Model.php'))->toBeTrue()
        ->and($dependencies->contains('/srv/shop/app/vendor/Helper.php'))->toBeFalse();
});

test('with no registered vendor directory, the one Composer\'s own loader sits in counts', function () {
    expect(DependencyFiles::withVendorDirectories([])->contains((string) new ReflectionClass(Str::class)->getFileName()))
        ->toBeTrue();
});
