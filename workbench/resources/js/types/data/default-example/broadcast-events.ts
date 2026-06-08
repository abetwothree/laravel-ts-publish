import type { EnumBroadcastEvent } from './app/events/EnumBroadcastEvent';
import type { MixedTypesEvent } from './app/events/MixedTypesEvent';
import type { MultiModelEvent } from './app/events/MultiModelEvent';
import type { OrderShipped } from './app/events/OrderShipped';
import type { PostPublishedEvent } from './app/events/PostPublishedEvent';
import type { PureEnumEvent } from './app/events/PureEnumEvent';
import type { ServerCreated } from './app/events/ServerCreated';
import type { StatusSynced } from './crm/events/StatusSynced';
import type { TeamMessageSent } from './app/events/TeamMessageSent';
import type { UserNotification } from './app/events/UserNotification';
import type { UserRegisteredEvent } from './app/events/UserRegisteredEvent';
import type { UserSynced as CrmUserSynced } from './crm/events/UserSynced';
import type { UserSynced as AppUserSynced } from './app/events/UserSynced';

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
