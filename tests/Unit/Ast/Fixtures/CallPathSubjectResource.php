<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Static calls on a named class and calls to the resource's own methods. */
final class CallPathSubjectResource extends JsonResource
{
    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'static_plain_date' => CallPathFactory::plainDate(),
            'static_interval' => CallPathFactory::interval(),
            'static_nullable_text' => CallPathFactory::nullableText(),
            'static_carbon' => CallPathFactory::carbon(),
            'static_base_model' => CallPathFactory::baseModel(),
            'static_auth_user' => CallPathFactory::authUser(),
            'static_abstract_model' => CallPathFactory::abstractModel(),
            'static_post' => CallPathFactory::post(),
            'static_arm' => $this->resource->flag ? CallPathFactory::baseModel() : CallPathFactory::post(),
            'static_date_arm' => $this->resource->flag ? CallPathFactory::plainDate() : null,
            'self_base_model' => self::ownStaticBaseModel(),
            'this_static_base_model' => $this::ownStaticBaseModel(),
            'own_base_model' => $this->ownBaseModel(),
            'own_auth_user' => $this->ownAuthUser(),
        ];
    }

    /** The framework's abstract base model. */
    public function ownBaseModel(): Model
    {
        return new AuthUser;
    }

    /** A concrete framework model. */
    public function ownAuthUser(): AuthUser
    {
        return new AuthUser;
    }

    /** A static framework base model. */
    public static function ownStaticBaseModel(): Model
    {
        return new AuthUser;
    }
}
