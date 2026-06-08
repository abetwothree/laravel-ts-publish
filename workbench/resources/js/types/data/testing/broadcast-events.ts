import type { EnumBroadcastEvent } from './workbench/app/events/EnumBroadcastEvent';
import type { MixedTypesEvent } from './workbench/app/events/MixedTypesEvent';
import type { MultiModelEvent } from './workbench/app/events/MultiModelEvent';
import type { OrderShipped } from './workbench/app/events/OrderShipped';
import type { PostPublishedEvent } from './workbench/app/events/PostPublishedEvent';
import type { PureEnumEvent } from './workbench/app/events/PureEnumEvent';
import type { ServerCreated } from './workbench/app/events/ServerCreated';
import type { TeamMessageSent } from './workbench/app/events/TeamMessageSent';
import type { UserNotification } from './workbench/app/events/UserNotification';
import type { UserRegisteredEvent } from './workbench/app/events/UserRegisteredEvent';

export type BroadcastEvent =
    | ".Workbench.App.Events.EnumBroadcastEvent"
    | ".Workbench.App.Events.MixedTypesEvent"
    | ".Workbench.App.Events.MultiModelEvent"
    | ".Workbench.App.Events.OrderShipped"
    | ".Workbench.App.Events.PostPublishedEvent"
    | ".Workbench.App.Events.PureEnumEvent"
    | "server.created"
    | ".Workbench.App.Events.TeamMessageSent"
    | ".Workbench.App.Events.UserNotification"
    | ".Workbench.App.Events.UserRegisteredEvent";

export const BroadcastEvents = Object.freeze({
    EnumBroadcastEvent: ".Workbench.App.Events.EnumBroadcastEvent",
    MixedTypesEvent: ".Workbench.App.Events.MixedTypesEvent",
    MultiModelEvent: ".Workbench.App.Events.MultiModelEvent",
    OrderShipped: ".Workbench.App.Events.OrderShipped",
    PostPublishedEvent: ".Workbench.App.Events.PostPublishedEvent",
    PureEnumEvent: ".Workbench.App.Events.PureEnumEvent",
    ServerCreated: "server.created",
    TeamMessageSent: ".Workbench.App.Events.TeamMessageSent",
    UserNotification: ".Workbench.App.Events.UserNotification",
    UserRegisteredEvent: ".Workbench.App.Events.UserRegisteredEvent",
} as const);

export type {
    EnumBroadcastEvent,
    MixedTypesEvent,
    MultiModelEvent,
    OrderShipped,
    PostPublishedEvent,
    PureEnumEvent,
    ServerCreated,
    TeamMessageSent,
    UserNotification,
    UserRegisteredEvent
};
