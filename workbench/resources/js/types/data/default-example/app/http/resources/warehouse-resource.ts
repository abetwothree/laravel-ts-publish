import type { BaseResource } from '@/types/base';
import type { ResourceRoutes } from '@/types/resources';
import type { Routable } from '@/types/routing';
import type { Timestamps } from '@/types/util';
import type { StatusType as CrmStatusType } from '../../../crm/enums';
import type { User as CrmUser } from '../../../crm/models';
import type { DatabaseNotification } from '../../../illuminate/notifications';
import type { ColorType, MembershipLevelType, PriorityType, RoleType, StatusType as AppStatusType } from '../../enums';
import type { Address, Comment, Image, Order, Post, Profile, Team, User as AppUser } from '../../models';

/**
 * Resource with no @mixin or TsResource — tests convention-based model guess. Also tests multiple TsExtends in parent class, trait, and locally.
 *
 * @see Workbench\App\Http\Resources\WarehouseResource
 */
export interface WarehouseResource extends BaseResource, ExtendableInterface, Omit<Timestamps, "created_at" | "updated_at">, ResourceRoutes, Pick<Routable, "store" | "update">
{
    id: number;
    name: string;
    color: ColorType | null;
    review_priority: AppStatusType | PriorityType | null;
    review_priority_typed: AppStatusType | PriorityType | null;
    review_priority_typed_short: AppStatusType | PriorityType | null;
    manager: AppUser | null;
    primary_contact: CrmUser | null;
    secondary_contact: CrmUser | null;
    last_user_activity_by: AppUser | CrmUser | null;
    last_user_activity_by_typed: AppUser | CrmUser | null;
    last_user_activity_by_typed_short: AppUser | CrmUser | null;
    last_user_activity_by_partial: { id: number; name: string } | null;
    last_user_activity_by_mostly: { email: string; company: string | null; status: CrmStatusType; created_at: string | null; updated_at: string | null; images: Image[] } | { email: string; email_verified_at: string | null; password: string; options: unknown[] | null; remember_token: string | null; created_at: string | null; updated_at: string | null; role: RoleType | null; membership_level: MembershipLevelType | null; phone: string | null; avatar: string | null; bio: string | null; settings: unknown[] | null; last_login_at: string | null; last_login_ip: string | null; initials: string; is_premium: boolean; profile: Profile | null; posts: Post[]; comments: Comment[]; orders: Order[]; addresses: Address[]; teams: Team[]; ownedTeams: Team[]; images: Image[]; notifications: DatabaseNotification[] } | null;
}
