<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\Crm\Http\Resources\UserResource as CrmUserResource;

/**
 * A test-only resource over HandoverLedger. It reads the accessors through `$this`, `parties` through a variable bound
 * to the model and through a relation's only(), and builds unions of two resources that share a name.
 *
 * @mixin HandoverLedger
 */
class HandoverLedgerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var HandoverLedger $ledger */
        $ledger = $this->resource;

        return [
            'who_and_crm' => $this->who_and_crm,
            'watchers_then_receiver' => $this->watchers_then_receiver,
            'crm_then_lead' => $this->crm_then_lead,
            'bound_parties' => $ledger->parties,
            'twin_parties' => $this->twin->only(['parties']),
            'resource_pair' => [
                'x' => $request->boolean('a') ? new UserResource($this->sender) : UserResource::collection($this->watchers),
                'y' => new CrmUserResource($this->receiver),
            ],
            'resource_arms' => [
                'w' => $request->boolean('a') ? ['r' => new UserResource($this->sender)] : ['r' => new UserResource($this->sender), 'q' => 1],
                'y' => new CrmUserResource($this->receiver),
            ],
        ];
    }
}
