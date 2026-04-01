/** Resource wrapping a unit enum (no backing type) to test the ->value fallback. Also accesses an unknown property to test the unknown enum property path. */
export interface UnitEnumResource
{
    name: string;
    value: string | number;
    custom: unknown;
}
