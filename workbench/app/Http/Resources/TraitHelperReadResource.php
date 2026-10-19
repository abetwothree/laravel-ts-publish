<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Concerns\QuotesBands;
use Workbench\App\Models\Label;
use Workbench\App\Services\BandQuoteService;

/**
 * Reads a trait-declared `: array` helper through three receivers: itself, a container instance and a static call.
 *
 * @mixin Label
 */
final class TraitHelperReadResource extends JsonResource
{
    use QuotesBands;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'own_quote' => $this->bandQuote(),
            'service_quote' => app(BandQuoteService::class)->bandQuote(),
            'static_quote' => BandQuoteService::staticBandQuote(),
        ];
    }
}
