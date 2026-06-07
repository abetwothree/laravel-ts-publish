import type { UserNotification } from './app/events/UserNotification';
import type { ServerCreated } from './app/events/ServerCreated';
import type { OrderShipped } from './app/events/OrderShipped';
import type { TeamMessageSent } from './app/events/TeamMessageSent';

export type BroadcastEvent =
    | ".Workbench.App.Events.UserNotification"
    | "server.created"
    | ".Workbench.App.Events.OrderShipped"
    | ".Workbench.App.Events.TeamMessageSent";

export const BroadcastEvents = {
    UserNotification: ".Workbench.App.Events.UserNotification" as const,
    ServerCreated: "server.created" as const,
    OrderShipped: ".Workbench.App.Events.OrderShipped" as const,
    TeamMessageSent: ".Workbench.App.Events.TeamMessageSent" as const,
} as const;

export type { UserNotification, ServerCreated, OrderShipped, TeamMessageSent };
