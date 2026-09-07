<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Illuminate\Http\Request;
use Inertia\Middleware;

/** Each spread body has its own signature: one really takes a Request, one only reuses the name. */
class SpreadShareMiddleware extends Middleware
{
    public function share(Request $request): array
    {
        return [
            ...$this->authProps($request),
            ...$this->decoyProps('nope'),
            'top' => $request->url(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function authProps(Request $req): array
    {
        return ['spreadUrl' => $req->url()];
    }

    /**
     * @return array<string, mixed>
     */
    protected function decoyProps(string $request): array
    {
        return ['decoy' => $request->url()];
    }
}
