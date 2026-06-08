import type { PostPublishedEvent } from './workbench/app/events/PostPublishedEvent';
import type { UserRegisteredEvent } from './workbench/app/events/UserRegisteredEvent';
import type { PureEnumEvent } from './workbench/app/events/PureEnumEvent';
import type { EnumBroadcastEvent } from './workbench/app/events/EnumBroadcastEvent';
import type { UserNotification } from './workbench/app/events/UserNotification';
import type { ServerCreated } from './workbench/app/events/ServerCreated';
import type { OrderShipped } from './workbench/app/events/OrderShipped';
import type { TeamMessageSent } from './workbench/app/events/TeamMessageSent';
import type { MultiModelEvent } from './workbench/app/events/MultiModelEvent';
import type { MixedTypesEvent } from './workbench/app/events/MixedTypesEvent';

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
