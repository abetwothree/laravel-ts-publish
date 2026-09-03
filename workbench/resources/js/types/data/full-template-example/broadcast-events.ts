import type { ComputedNameEvent } from './app/events/ComputedNameEvent';
import type { DeclaredPropsEvent } from './app/events/DeclaredPropsEvent';
import type { EnumBroadcastEvent } from './app/events/EnumBroadcastEvent';
import type { MixedTypesEvent } from './app/events/MixedTypesEvent';
import type { MultiModelEvent } from './app/events/MultiModelEvent';
import type { OrderShipped } from './app/events/OrderShipped';
import type { PayloadDiffersEvent } from './app/events/PayloadDiffersEvent';
import type { PostPublishedEvent } from './app/events/PostPublishedEvent';
import type { PureEnumEvent } from './app/events/PureEnumEvent';
import type { ReportSynced } from './app/events/ReportSynced';
import type { ServerCreated } from './app/events/ServerCreated';
import type { StatusSynced } from './crm/events/StatusSynced';
import type { TeamMessageSent } from './app/events/TeamMessageSent';
import type { UserNotification } from './app/events/UserNotification';
import type { UserRegisteredEvent } from './app/events/UserRegisteredEvent';
import type { UserSynced as AppUserSynced } from './app/events/UserSynced';
import type { UserSynced as CrmUserSynced } from './crm/events/UserSynced';

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
