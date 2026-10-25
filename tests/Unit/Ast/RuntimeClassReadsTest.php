<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverBaseDto;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverChildDto;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt\Expression;
use Workbench\App\Http\Resources\NoticeResource;
use Workbench\App\ValueObjects\PanelPayload;

/** Parse one `$this->m()` call written as a statement. */
function runtimeClassCall(string $php): Expr
{
    $statement = new AstParser()->parseSource('<?php '.$php.';')[0];
    assert($statement instanceof Expression);

    return $statement->expr;
}

it('reads a method an inherited body calls on the runtime class', function () {
    expect(analyzedKeyTypes(PanelPayload::class))->toMatchArray(['title' => 'string', 'child_only' => 'boolean'])
        ->and(analyzedKeyTypes(PanelPayload::class))->not->toHaveKey('base_only')
        ->and(json_encode(new PanelPayload()->toArray()))->toContain('"title":"x","child_only":true');
});

it('walks the with() and the spread helper the runtime class declares', function () {
    expect(analyzedKeyTypes(NoticeResource::class))->toMatchArray([
        'headline' => 'string', 'child_only' => 'boolean', 'meta' => 'number',
    ])->and(analyzedKeyTypes(NoticeResource::class))->not->toHaveKey('base_only')
        ->and((new NoticeResource(null))->resolve(request()))->toMatchArray(['headline' => 'x', 'child_only' => true, 'meta' => 1])
        ->and((new NoticeResource(null))->resolve(request()))->not->toHaveKey('base_only');
});

it('keeps a member the declaring class declares private', function () {
    expect(analyzedKeyTypes(PanelPayload::class)['weight'])->toBe('number')
        ->and(new PanelPayload()->toArray()['weight'])->toBe(1);
});

it('types the receiver an inherited body\'s $this->m() returns as the runtime class runs it', function () {
    $scope = new AnalysisScope(new ReflectionClass(ReceiverBaseDto::class));
    $scope->runtimeReflection = new ReflectionClass(ReceiverChildDto::class);
    $resolver = resolve(ReceiverClassResolver::class);

    expect($resolver->resolve(runtimeClassCall('$this->fresh()'), $scope)?->classes)->toBe([ReceiverChildDto::class])
        ->and($resolver->resolve(runtimeClassCall('$this->copy()'), $scope)?->classes)->toBe([ReceiverBaseDto::class])
        ->and(new ReceiverChildDto()->fresh())->toBeInstanceOf(ReceiverChildDto::class)
        ->and(new ReceiverChildDto()->copy())->not->toBeInstanceOf(ReceiverChildDto::class);
});
