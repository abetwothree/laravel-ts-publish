import { type AsEnum } from '@tolki/ts';

import { Status } from '../../../enums';

/**
 * Nested literals whose keys follow a sibling key's value, one condition form per key (GH-157). Each `*_kept` key is
 * a control that stays one object: its test reads no sibling with a finite set of values, or cannot be decided.
 *
 * @see Workbench\App\Http\Resources\Discriminated\DiscriminantFormsResource
 */
export interface DiscriminantFormsResource
{
    negated: { pinned: true; v: number } | { pinned: false; v: string | null };
    strict_true: { pinned: true; v: string } | { pinned: false; v: null };
    strict_false: { pinned: true; v: number } | { pinned: false; v: string | null };
    case_compare: { status: 0; v: null } | { status: 1; v: string | null };
    case_not_equal: { status: 0; v: number } | { status: 1; v: string };
    value_read: { status: 0; v: string | null } | { status: 1; v: number };
    unit_enum_default: { visibility: 'Public'; v: string } | { visibility: 'Private' | 'Protected' | 'Internal' | 'Draft' | null; v: null };
    match_true: { status: 0; v: string | null } | { status: 1; v: number };
    when_dependent: { pinned: true; until: string | null } | { pinned: false; until?: never };
    when_closure: { pinned: true; until: number } | { pinned: false; until?: never };
    unless_default: { pinned: true; v: number } | { pinned: false; v: string | null };
    two_dependents: { a: string; pinned: true; b: null } | { a: null; pinned: false; b: string | null };
    nested_ternary: { pinned: true; v: string } | { pinned: false; v: string | number | null };
    two_discriminants: { pinned: true; status: 0; a: string; b: string | null } | { pinned: true; status: 1; a: string; b: number } | { pinned: false; status: 0; a: null; b: string | null } | { pinned: false; status: 1; a: null; b: number };
    throw_arm: { priority: 0; v: string } | { priority: 2; v: number };
    shared_keys: { id: number; pinned: true; v: string; title: string } | { id: number; pinned: false; v: number; title: string };
    cast_discriminant_kept: { pinned: boolean; v: string | null };
    method_condition_kept: { featured: boolean; v: string | null };
    unpublished_condition_kept: { id: number; v: string | null };
    string_discriminant_kept: { title: string; v: number | null };
    wrapped_discriminant_kept: { status: AsEnum<typeof Status>; v: number | null };
    optional_discriminant_kept: { pinned?: boolean; v: string | null };
    undecidable_kept: { pinned: boolean; v: string | null };
    same_arms_kept: { pinned: boolean; v: number };
    untypable_arm_kept: { pinned: boolean; v: null };
}
