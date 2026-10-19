<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;
use Override;

/**
 * A test-only model transformer that adjusts Depot's relations after the parent's transform(), as a project's own
 * transformer does: the `supervisor` relation goes and a `latest_order` relation comes.
 */
class SwappedRelationModelTransformer extends ModelTransformer
{
    #[Override]
    public function transform(): self
    {
        parent::transform();

        unset($this->relations['supervisor']);
        $this->relations['latest_order'] = ['type' => 'Order | null', 'description' => ''];

        return $this;
    }
}
