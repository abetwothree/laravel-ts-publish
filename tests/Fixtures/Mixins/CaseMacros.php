<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Mixins;

use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;

/** Resource macros returning a plain enum case behind a vague declaration: two methods and a closure. */
class CaseMacros
{
    /** Registers `caseDraft` and `caseOuter` as method macros, and `caseHelper` as a closure calling a vague helper. */
    public static function register(): void
    {
        JsonResource::macro('caseDraft', [new self, 'draft']);
        JsonResource::macro('caseOuter', [new self, 'outer']);
        JsonResource::macro('caseHelper', function () {
            return $this->draft();
        });
    }

    /** A case behind a mixed declaration. */
    public function draft(): mixed
    {
        return Status::Draft;
    }

    /** The case another vague method returns. */
    public function outer(): mixed
    {
        return $this->draft();
    }
}
