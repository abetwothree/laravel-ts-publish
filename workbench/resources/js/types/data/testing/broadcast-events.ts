import type { ComputedNameEvent } from './workbench/app/events/ComputedNameEvent';
import type { DeclaredPropsEvent } from './workbench/app/events/DeclaredPropsEvent';
import type { DispatchRelayed } from './workbench/app/events/DispatchRelayed';
import type { DocblockShapedEvent } from './workbench/app/events/DocblockShapedEvent';
import type { EnumBroadcastEvent } from './workbench/app/events/EnumBroadcastEvent';
import type { FacilityAudited } from './workbench/app/events/FacilityAudited';
import type { ManifestAssembled } from './workbench/app/events/ManifestAssembled';
import type { MixedTypesEvent } from './workbench/app/events/MixedTypesEvent';
import type { MultiModelEvent } from './workbench/app/events/MultiModelEvent';
import type { OrderShipped } from './workbench/app/events/OrderShipped';
import type { PayloadDiffersEvent } from './workbench/app/events/PayloadDiffersEvent';
import type { PostPublishedEvent } from './workbench/app/events/PostPublishedEvent';
import type { PureEnumEvent } from './workbench/app/events/PureEnumEvent';
import type { ReportSynced } from './workbench/app/events/ReportSynced';
import type { ReviewerCastEvent } from './workbench/app/events/ReviewerCastEvent';
import type { SameBasenameModelEvent } from './workbench/app/events/SameBasenameModelEvent';
import type { ServerCreated } from './workbench/app/events/ServerCreated';
import type { StatusSynced } from './workbench/crm/events/StatusSynced';
import type { TaggedPayloadEvent } from './workbench/app/events/TaggedPayloadEvent';
import type { TeamMessageSent } from './workbench/app/events/TeamMessageSent';
import type { TeamRosterSynced } from './workbench/app/events/TeamRosterSynced';
import type { UserNotification } from './workbench/app/events/UserNotification';
import type { UserRegisteredEvent } from './workbench/app/events/UserRegisteredEvent';
import type { UserSynced as AppUserSynced } from './workbench/app/events/UserSynced';
import type { UserSynced as CrmUserSynced } from './workbench/crm/events/UserSynced';

export type BroadcastEvent =
    | '.Workbench.App.Events.ComputedNameEvent'
    | '.Workbench.App.Events.DeclaredPropsEvent'
    | '.Workbench.App.Events.DispatchRelayed'
    | '.Workbench.App.Events.DocblockShapedEvent'
    | '.Workbench.App.Events.EnumBroadcastEvent'
    | '.Workbench.App.Events.FacilityAudited'
    | '.Workbench.App.Events.ManifestAssembled'
    | '.Workbench.App.Events.MixedTypesEvent'
    | '.Workbench.App.Events.MultiModelEvent'
    | '.Workbench.App.Events.OrderShipped'
    | '.Workbench.App.Events.PayloadDiffersEvent'
    | '.Workbench.App.Events.PostPublishedEvent'
    | '.Workbench.App.Events.PureEnumEvent'
    | '.Workbench.App.Events.ReportSynced'
    | '.Workbench.App.Events.ReviewerCastEvent'
    | '.Workbench.App.Events.SameBasenameModelEvent'
    | 'server.created'
    | '.Workbench.Crm.Events.StatusSynced'
    | '.Workbench.App.Events.TaggedPayloadEvent'
    | '.Workbench.App.Events.TeamMessageSent'
    | '.Workbench.App.Events.TeamRosterSynced'
    | '.Workbench.App.Events.UserNotification'
    | '.Workbench.App.Events.UserRegisteredEvent'
    | '.Workbench.App.Events.UserSynced'
    | '.Workbench.Crm.Events.UserSynced';

export const BroadcastEvents = Object.freeze({
    ComputedNameEvent: '.Workbench.App.Events.ComputedNameEvent',
    DeclaredPropsEvent: '.Workbench.App.Events.DeclaredPropsEvent',
    DispatchRelayed: '.Workbench.App.Events.DispatchRelayed',
    DocblockShapedEvent: '.Workbench.App.Events.DocblockShapedEvent',
    EnumBroadcastEvent: '.Workbench.App.Events.EnumBroadcastEvent',
    FacilityAudited: '.Workbench.App.Events.FacilityAudited',
    ManifestAssembled: '.Workbench.App.Events.ManifestAssembled',
    MixedTypesEvent: '.Workbench.App.Events.MixedTypesEvent',
    MultiModelEvent: '.Workbench.App.Events.MultiModelEvent',
    OrderShipped: '.Workbench.App.Events.OrderShipped',
    PayloadDiffersEvent: '.Workbench.App.Events.PayloadDiffersEvent',
    PostPublishedEvent: '.Workbench.App.Events.PostPublishedEvent',
    PureEnumEvent: '.Workbench.App.Events.PureEnumEvent',
    ReportSynced: '.Workbench.App.Events.ReportSynced',
    ReviewerCastEvent: '.Workbench.App.Events.ReviewerCastEvent',
    SameBasenameModelEvent: '.Workbench.App.Events.SameBasenameModelEvent',
    ServerCreated: 'server.created',
    StatusSynced: '.Workbench.Crm.Events.StatusSynced',
    TaggedPayloadEvent: '.Workbench.App.Events.TaggedPayloadEvent',
    TeamMessageSent: '.Workbench.App.Events.TeamMessageSent',
    TeamRosterSynced: '.Workbench.App.Events.TeamRosterSynced',
    UserNotification: '.Workbench.App.Events.UserNotification',
    UserRegisteredEvent: '.Workbench.App.Events.UserRegisteredEvent',
    AppUserSynced: '.Workbench.App.Events.UserSynced',
    CrmUserSynced: '.Workbench.Crm.Events.UserSynced',
} as const);

export type {
    ComputedNameEvent,
    DeclaredPropsEvent,
    DispatchRelayed,
    DocblockShapedEvent,
    EnumBroadcastEvent,
    FacilityAudited,
    ManifestAssembled,
    MixedTypesEvent,
    MultiModelEvent,
    OrderShipped,
    PayloadDiffersEvent,
    PostPublishedEvent,
    PureEnumEvent,
    ReportSynced,
    ReviewerCastEvent,
    SameBasenameModelEvent,
    ServerCreated,
    StatusSynced,
    TaggedPayloadEvent,
    TeamMessageSent,
    TeamRosterSynced,
    UserNotification,
    UserRegisteredEvent,
    AppUserSynced,
    CrmUserSynced
};
