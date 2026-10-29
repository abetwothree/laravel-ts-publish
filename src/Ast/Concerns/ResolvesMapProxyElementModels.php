<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Enumerable;
use PhpParser\Node\Expr;

/**
 * Resolve the element model behind a `->map` proxy receiver for RelationFilterHandler.
 *
 * @internal
 */
trait ResolvesMapProxyElementModels
{
    /**
     * Resolve the element model behind a `->map` proxy receiver: that of the collection the receiver resolver names,
     * such as a to-many whenLoaded parameter, `$this->relation`, a local holding one, `getRelation()` or a query's
     * get(); null for any other receiver, such as a singular relation's variable.
     *
     * @return class-string<Model>|null
     */
    protected function resolveMapProxyElementModel(Expr $receiver, AnalysisScope $scope): ?string
    {
        $held = resolve(ReceiverClassResolver::class)->resolve($receiver, $scope);

        return $held !== null && $held->within([Enumerable::class]) ? $held->elementModel : null;
    }
}
