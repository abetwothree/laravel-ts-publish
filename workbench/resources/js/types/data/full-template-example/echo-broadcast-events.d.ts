import type { PostPublishedEvent } from './app/events/PostPublishedEvent';
import type { UserRegisteredEvent } from './app/events/UserRegisteredEvent';
import type { PureEnumEvent } from './app/events/PureEnumEvent';
import type { EnumBroadcastEvent } from './app/events/EnumBroadcastEvent';
import type { UserNotification } from './app/events/UserNotification';
import type { ServerCreated } from './app/events/ServerCreated';
import type { OrderShipped } from './app/events/OrderShipped';
import type { TeamMessageSent } from './app/events/TeamMessageSent';
import type { MultiModelEvent } from './app/events/MultiModelEvent';
import type { MixedTypesEvent } from './app/events/MixedTypesEvent';

declare module "@laravel/echo" {
    interface Events {
        ".Workbench.App.Events.PostPublishedEvent": PostPublishedEvent;
        ".Workbench.App.Events.UserRegisteredEvent": UserRegisteredEvent;
        ".Workbench.App.Events.PureEnumEvent": PureEnumEvent;
        ".Workbench.App.Events.EnumBroadcastEvent": EnumBroadcastEvent;
        ".Workbench.App.Events.UserNotification": UserNotification;
        "server.created": ServerCreated;
        ".Workbench.App.Events.OrderShipped": OrderShipped;
        ".Workbench.App.Events.TeamMessageSent": TeamMessageSent;
        ".Workbench.App.Events.MultiModelEvent": MultiModelEvent;
        ".Workbench.App.Events.MixedTypesEvent": MixedTypesEvent;
    }
}
