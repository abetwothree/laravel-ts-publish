<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Profile;

/**
 * Exercises: multiple whenHas on different column types, multiple whenNotNull.
 *
 * @mixin Profile
 */
class ProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bio' => $this->bio,
            'avatar_url' => $this->avatar_url,
            'date_of_birth' => $this->whenHas('date_of_birth'),
            'website' => $this->whenHas('website'),
            'phone_number' => $this->whenHas('phone_number'),
            'social_links' => $this->whenNotNull($this->social_links),
            'timezone' => $this->whenNotNull($this->timezone),
            'locale' => $this->whenNotNull($this->locale),
        ];
    }
}
