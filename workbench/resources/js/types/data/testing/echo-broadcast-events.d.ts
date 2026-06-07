import type { UserNotification } from './workbench/app/events/UserNotification';
import type { ServerCreated } from './workbench/app/events/ServerCreated';
import type { OrderShipped } from './workbench/app/events/OrderShipped';
import type { TeamMessageSent } from './workbench/app/events/TeamMessageSent';

declare module "@laravel/echo" {
    interface Events {
        ".Workbench.App.Events.UserNotification": UserNotification;
        "server.created": ServerCreated;
        ".Workbench.App.Events.OrderShipped": OrderShipped;
        ".Workbench.App.Events.TeamMessageSent": TeamMessageSent;
    }
}
