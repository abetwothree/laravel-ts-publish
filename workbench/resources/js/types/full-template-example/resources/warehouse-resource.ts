import type { BaseResource } from '@/types/base';
import type { ResourceRoutes } from '@/types/resources';
import type { Routable } from '@/types/routing';
import type { Timestamps } from '@/types/util';
import type { ColorType, MembershipLevelType, PriorityType, RoleType, StatusType as CrmStatusType, StatusType as WorkbenchStatusType } from '../enums';
import type { Address, Comment, DatabaseNotification, Image, Order, Post, Profile, Team, User as CrmUser, User as WorkbenchUser } from '../models';

/** Resource with no @mixin or TsResource — tests convention-based model guess. Also tests multiple TsExtends in parent class, trait, and locally. */
export interface WarehouseResource extends BaseResource, ExtendableInterface, Omit<Timestamps, "created_at" | "updated_at">, ResourceRoutes, Pick<Routable, "store" | "update">
{
    id: number;
    name: string;
    color: ColorType | null;
    review_priority: WorkbenchStatusType | PriorityType | null;
    review_priority_typed: WorkbenchStatusType | PriorityType | null;
    review_priority_typed_short: WorkbenchStatusType | PriorityType | null;
    manager: WorkbenchUser | null;
    primary_contact: CrmUser | null;
    secondary_contact: CrmUser | null;
    last_user_activity_by: WorkbenchUser | CrmUser | null;
    last_user_activity_by_typed: WorkbenchUser | CrmUser | null;
    last_user_activity_by_typed_short: WorkbenchUser | CrmUser | null;
    last_user_activity_by_partial: { id: number; name: string } | null;
    last_user_activity_by_mostly: { email: string; company: string | null; status: CrmStatusType; created_at: string | null; updated_at: string | null } | { email: string; email_verified_at: string | null; password: string; options: unknown[] | null; remember_token: string | null; created_at: string | null; updated_at: string | null; role: RoleType | null; membership_level: MembershipLevelType | null; phone: string | null; avatar: string | null; bio: string | null; settings: unknown[] | null; last_login_at: string | null; last_login_ip: string | null; initials: string; is_premium: boolean; profile: Profile | null; posts: Post[]; comments: Comment[]; orders: Order[]; addresses: Address[]; teams: Team[]; ownedTeams: Team[]; images: Image[]; notifications: DatabaseNotification[] } | null;
}
