import type { User } from '../models';

/** Exercises: reading model from @extends ParentClass<Model> in docblock Do not change, it needs to match the AddressMixinResource exactly */
export interface AddressExtendsResource
{
    morphValue: string;
    id: number;
    full_address: string | null;
    latitude?: number | null;
    longitude?: number | null;
    user?: User;
}
