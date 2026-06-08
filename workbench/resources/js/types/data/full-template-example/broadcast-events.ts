import type { EnumBroadcastEvent } from './app/events/EnumBroadcastEvent';
import type { MixedTypesEvent } from './app/events/MixedTypesEvent';
import type { MultiModelEvent } from './app/events/MultiModelEvent';
import type { OrderShipped } from './app/events/OrderShipped';
import type { PostPublishedEvent } from './app/events/PostPublishedEvent';
import type { PureEnumEvent } from './app/events/PureEnumEvent';
import type { ServerCreated } from './app/events/ServerCreated';
import type { TeamMessageSent } from './app/events/TeamMessageSent';
import type { UserNotification } from './app/events/UserNotification';
import type { UserRegisteredEvent } from './app/events/UserRegisteredEvent';

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
