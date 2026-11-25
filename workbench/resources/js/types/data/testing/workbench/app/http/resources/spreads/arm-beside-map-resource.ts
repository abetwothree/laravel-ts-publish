import type { User as CrmUser } from '../../../../crm/models';
import type { User as WorkbenchUser } from '../../../models';
import type { UserResource } from '..';
import type { IdTitleArmResource, NullNoteArmResource, StatusWrapArmResource, TagSignatureArmResource, TitleFlagArmResource } from '.';

/**
 * Named keys and resource or model arms beside typed maps, before and after them (GH-161 D2, D3).
 *
 * `three_model_spreads` is a guard whose golden is develop's known-short reading until #80 l64: of two same-named
 * users it keeps one arm.
 *
 * @see Workbench\App\Http\Resources\Spreads\ArmBesideMapResource
 */
export interface ArmBesideMapResource
{
    map_before_unfit_key: Omit<Record<string, string>, 'a'> & { a: number };
    model_key_before_map: { user: WorkbenchUser | string | null };
    arm_before_map: Omit<TitleFlagArmResource, keyof Record<string, string>> & Record<string, string>;
    map_before_arm: Omit<Record<string, string>, keyof TitleFlagArmResource> & TitleFlagArmResource;
    arm_then_two_maps: Omit<IdTitleArmResource, keyof Record<string, string | number>> & Record<string, string | number>;
    map_arm_map: Omit<IdTitleArmResource, keyof Record<string, string | number>> & Record<string, string | number>;
    two_maps_then_arm: Omit<Record<string, string | number>, keyof IdTitleArmResource> & IdTitleArmResource;
    key_arm_two_maps: Omit<IdTitleArmResource, 'n' | 'id' | 'by' | keyof Record<string, string | number>> & Omit<Record<string, string | number>, 'n' | 'id' | 'by'> & { n: number | string; id: number | string; by: string | number };
    two_maps_then_null_arm: Omit<Record<string, string | number>, keyof NullNoteArmResource> & NullNoteArmResource;
    null_arm_then_two_maps: Omit<NullNoteArmResource, keyof Record<string, string | number>> & Record<string, string | number>;
    maybe_map_before_user_arm: UserResource;
    arm_then_maybe_map: TitleFlagArmResource;
    maybe_map_then_arm: TitleFlagArmResource;
    signature_arm_then_map: Omit<TagSignatureArmResource, keyof Record<string, string>> & Record<string, string>;
    signature_arm_then_maybe_key: Omit<TagSignatureArmResource, 'name'> & { name?: number };
    wrap_arm_then_map: Omit<StatusWrapArmResource, keyof Record<string, string>> & Record<string, string>;
    wrap_arm_then_maybe_key: Omit<StatusWrapArmResource, 'name'> & { name?: number };
    same_named_arm_after_map: Omit<Record<string, WorkbenchUser>, keyof CrmUser> & CrmUser;
    three_model_spreads: Omit<CrmUser, keyof WorkbenchUser> & CrmUser;
}
