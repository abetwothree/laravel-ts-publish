import type { ComputedNameEvent } from './workbench/app/events/ComputedNameEvent';
import type { DeclaredPropsEvent } from './workbench/app/events/DeclaredPropsEvent';
import type { EnumBroadcastEvent } from './workbench/app/events/EnumBroadcastEvent';
import type { MixedTypesEvent } from './workbench/app/events/MixedTypesEvent';
import type { MultiModelEvent } from './workbench/app/events/MultiModelEvent';
import type { OrderShipped } from './workbench/app/events/OrderShipped';
import type { PayloadDiffersEvent } from './workbench/app/events/PayloadDiffersEvent';
import type { PostPublishedEvent } from './workbench/app/events/PostPublishedEvent';
import type { PureEnumEvent } from './workbench/app/events/PureEnumEvent';
import type { ReportSynced } from './workbench/app/events/ReportSynced';
import type { ServerCreated } from './workbench/app/events/ServerCreated';
import type { StatusSynced } from './workbench/crm/events/StatusSynced';
import type { TeamMessageSent } from './workbench/app/events/TeamMessageSent';
import type { UserNotification } from './workbench/app/events/UserNotification';
import type { UserRegisteredEvent } from './workbench/app/events/UserRegisteredEvent';
import type { UserSynced as AppUserSynced } from './workbench/app/events/UserSynced';
import type { UserSynced as CrmUserSynced } from './workbench/crm/events/UserSynced';

export type BroadcastEvent =
    | '.Workbench.App.Events.ComputedNameEvent'
    | '.Workbench.App.Events.DeclaredPropsEvent'
    | '.Workbench.App.Events.EnumBroadcastEvent'
    | '.Workbench.App.Events.MixedTypesEvent'
    | '.Workbench.App.Events.MultiModelEvent'
    | '.Workbench.App.Events.OrderShipped'
    | '.Workbench.App.Events.PayloadDiffersEvent'
    | '.Workbench.App.Events.PostPublishedEvent'
    | '.Workbench.App.Events.PureEnumEvent'
    | '.Workbench.App.Events.ReportSynced'
    | 'server.created'
    | '.Workbench.Crm.Events.StatusSynced'
    | '.Workbench.App.Events.TeamMessageSent'
    | '.Workbench.App.Events.UserNotification'
    | '.Workbench.App.Events.UserRegisteredEvent'
    | '.Workbench.App.Events.UserSynced'
    | '.Workbench.Crm.Events.UserSynced';

export const BroadcastEvents = Object.freeze({
    ComputedNameEvent: '.Workbench.App.Events.ComputedNameEvent',
    DeclaredPropsEvent: '.Workbench.App.Events.DeclaredPropsEvent',
    EnumBroadcastEvent: '.Workbench.App.Events.EnumBroadcastEvent',
    MixedTypesEvent: '.Workbench.App.Events.MixedTypesEvent',
    MultiModelEvent: '.Workbench.App.Events.MultiModelEvent',
    OrderShipped: '.Workbench.App.Events.OrderShipped',
    PayloadDiffersEvent: '.Workbench.App.Events.PayloadDiffersEvent',
    PostPublishedEvent: '.Workbench.App.Events.PostPublishedEvent',
    PureEnumEvent: '.Workbench.App.Events.PureEnumEvent',
    ReportSynced: '.Workbench.App.Events.ReportSynced',
    ServerCreated: 'server.created',
    StatusSynced: '.Workbench.Crm.Events.StatusSynced',
    TeamMessageSent: '.Workbench.App.Events.TeamMessageSent',
    UserNotification: '.Workbench.App.Events.UserNotification',
    UserRegisteredEvent: '.Workbench.App.Events.UserRegisteredEvent',
    AppUserSynced: '.Workbench.App.Events.UserSynced',
    CrmUserSynced: '.Workbench.Crm.Events.UserSynced',
} as const);

export type {
    ComputedNameEvent,
    DeclaredPropsEvent,
    EnumBroadcastEvent,
    MixedTypesEvent,
    MultiModelEvent,
    OrderShipped,
    PayloadDiffersEvent,
    PostPublishedEvent,
    PureEnumEvent,
    ReportSynced,
    ServerCreated,
    StatusSynced,
    TeamMessageSent,
    UserNotification,
    UserRegisteredEvent,
    AppUserSynced,
    CrmUserSynced
};
