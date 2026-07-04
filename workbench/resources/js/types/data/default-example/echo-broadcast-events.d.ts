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

declare module "@laravel/echo" {
    interface Events {
        ".Workbench.App.Events.EnumBroadcastEvent": EnumBroadcastEvent;
        ".Workbench.App.Events.MixedTypesEvent": MixedTypesEvent;
        ".Workbench.App.Events.MultiModelEvent": MultiModelEvent;
        ".Workbench.App.Events.OrderShipped": OrderShipped;
        ".Workbench.App.Events.PostPublishedEvent": PostPublishedEvent;
        ".Workbench.App.Events.PureEnumEvent": PureEnumEvent;
        "server.created": ServerCreated;
        ".Workbench.Crm.Events.StatusSynced": StatusSynced;
        ".Workbench.App.Events.TeamMessageSent": TeamMessageSent;
        ".Workbench.App.Events.UserNotification": UserNotification;
        ".Workbench.App.Events.UserRegisteredEvent": UserRegisteredEvent;
        ".Workbench.Crm.Events.UserSynced": CrmUserSynced;
        ".Workbench.App.Events.UserSynced": AppUserSynced;
    }
}
