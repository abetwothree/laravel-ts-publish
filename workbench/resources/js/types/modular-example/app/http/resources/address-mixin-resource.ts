import type { User } from '../../models';

/** Exercises: reading model from @mixin ModelClass in docblock Do not change, it needs to match the AddressExtendsResource exactly */
export interface AddressMixinResource
{
    morphValue: string;
    id: number;
    full_address: string | null;
    latitude?: number | null;
    longitude?: number | null;
    user?: User;
}
