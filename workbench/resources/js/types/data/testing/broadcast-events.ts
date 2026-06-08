import type { EnumBroadcastEvent } from './workbench/app/events/EnumBroadcastEvent';
import type { MixedTypesEvent } from './workbench/app/events/MixedTypesEvent';
import type { MultiModelEvent } from './workbench/app/events/MultiModelEvent';
import type { OrderShipped } from './workbench/app/events/OrderShipped';
import type { PostPublishedEvent } from './workbench/app/events/PostPublishedEvent';
import type { PureEnumEvent } from './workbench/app/events/PureEnumEvent';
import type { ServerCreated } from './workbench/app/events/ServerCreated';
import type { StatusSynced } from './workbench/crm/events/StatusSynced';
import type { TeamMessageSent } from './workbench/app/events/TeamMessageSent';
import type { UserNotification } from './workbench/app/events/UserNotification';
import type { UserRegisteredEvent } from './workbench/app/events/UserRegisteredEvent';
import type { UserSynced as CrmUserSynced } from './workbench/crm/events/UserSynced';
import type { UserSynced as AppUserSynced } from './workbench/app/events/UserSynced';

export type BroadcastEvent =
    | '.Workbench.App.Events.EnumBroadcastEvent'
    | '.Workbench.App.Events.MixedTypesEvent'
    | '.Workbench.App.Events.MultiModelEvent'
    | '.Workbench.App.Events.OrderShipped'
    | '.Workbench.App.Events.PostPublishedEvent'
    | '.Workbench.App.Events.PureEnumEvent'
    | 'server.created'
    | '.Workbench.Crm.Events.StatusSynced'
    | '.Workbench.App.Events.TeamMessageSent'
    | '.Workbench.App.Events.UserNotification'
    | '.Workbench.App.Events.UserRegisteredEvent'
    | '.Workbench.Crm.Events.UserSynced'
    | '.Workbench.App.Events.UserSynced';

export const BroadcastEvents = Object.freeze({
    EnumBroadcastEvent: '.Workbench.App.Events.EnumBroadcastEvent',
    MixedTypesEvent: '.Workbench.App.Events.MixedTypesEvent',
    MultiModelEvent: '.Workbench.App.Events.MultiModelEvent',
    OrderShipped: '.Workbench.App.Events.OrderShipped',
    PostPublishedEvent: '.Workbench.App.Events.PostPublishedEvent',
    PureEnumEvent: '.Workbench.App.Events.PureEnumEvent',
    ServerCreated: 'server.created',
    StatusSynced: '.Workbench.Crm.Events.StatusSynced',
    TeamMessageSent: '.Workbench.App.Events.TeamMessageSent',
    UserNotification: '.Workbench.App.Events.UserNotification',
    UserRegisteredEvent: '.Workbench.App.Events.UserRegisteredEvent',
    CrmUserSynced: '.Workbench.Crm.Events.UserSynced',
    AppUserSynced: '.Workbench.App.Events.UserSynced',
} as const);

export type {
    EnumBroadcastEvent,
    MixedTypesEvent,
    MultiModelEvent,
    OrderShipped,
    PostPublishedEvent,
    PureEnumEvent,
    ServerCreated,
    StatusSynced,
    TeamMessageSent,
    UserNotification,
    UserRegisteredEvent,
    CrmUserSynced,
    AppUserSynced
};
