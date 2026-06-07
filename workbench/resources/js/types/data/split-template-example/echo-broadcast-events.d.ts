import type { UserNotification } from './app/events/UserNotification';
import type { ServerCreated } from './app/events/ServerCreated';
import type { OrderShipped } from './app/events/OrderShipped';
import type { TeamMessageSent } from './app/events/TeamMessageSent';

declare module "@laravel/echo" {
    interface Events {
        ".Workbench.App.Events.UserNotification": UserNotification;
        "server.created": ServerCreated;
        ".Workbench.App.Events.OrderShipped": OrderShipped;
        ".Workbench.App.Events.TeamMessageSent": TeamMessageSent;
    }
}
