import type { UserResource } from '.';

/** Edge-case resource exercising unusual but valid patterns for AST analyzer guard clauses. */
export interface QuirkyResource
{
    id: number;
    flag?: unknown;
    normal_merge_key?: number;
    formatted: unknown;
    plain_user: UserResource;
    empty_user: UserResource;
    empty_enum: unknown;
    fcc_enum: unknown;
    not_enum: unknown;
    uncast_enum: unknown;
    fake_field: unknown;
    fake_relation?: unknown;
}
