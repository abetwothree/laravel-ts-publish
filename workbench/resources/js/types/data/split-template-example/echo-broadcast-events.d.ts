import type { ComputedNameEvent } from './app/events/ComputedNameEvent';
import type { DeclaredPropsEvent } from './app/events/DeclaredPropsEvent';
import type { EnumBroadcastEvent } from './app/events/EnumBroadcastEvent';
import type { MixedTypesEvent } from './app/events/MixedTypesEvent';
import type { MultiModelEvent } from './app/events/MultiModelEvent';
import type { OrderShipped } from './app/events/OrderShipped';
import type { PayloadDiffersEvent } from './app/events/PayloadDiffersEvent';
import type { PostPublishedEvent } from './app/events/PostPublishedEvent';
import type { PureEnumEvent } from './app/events/PureEnumEvent';
import type { ReportSynced } from './app/events/ReportSynced';
import type { ServerCreated } from './app/events/ServerCreated';
import type { StatusSynced } from './crm/events/StatusSynced';
import type { TeamMessageSent } from './app/events/TeamMessageSent';
import type { UserNotification } from './app/events/UserNotification';
import type { UserRegisteredEvent } from './app/events/UserRegisteredEvent';
import type { UserSynced as AppUserSynced } from './app/events/UserSynced';
import type { UserSynced as CrmUserSynced } from './crm/events/UserSynced';

declare module "@laravel/echo" {
    interface Events {
        ".Workbench.App.Events.ComputedNameEvent": ComputedNameEvent;
        ".Workbench.App.Events.DeclaredPropsEvent": DeclaredPropsEvent;
        ".Workbench.App.Events.EnumBroadcastEvent": EnumBroadcastEvent;
        ".Workbench.App.Events.MixedTypesEvent": MixedTypesEvent;
        ".Workbench.App.Events.MultiModelEvent": MultiModelEvent;
        ".Workbench.App.Events.OrderShipped": OrderShipped;
        ".Workbench.App.Events.PayloadDiffersEvent": PayloadDiffersEvent;
        ".Workbench.App.Events.PostPublishedEvent": PostPublishedEvent;
        ".Workbench.App.Events.PureEnumEvent": PureEnumEvent;
        ".Workbench.App.Events.ReportSynced": ReportSynced;
        "server.created": ServerCreated;
        ".Workbench.Crm.Events.StatusSynced": StatusSynced;
        ".Workbench.App.Events.TeamMessageSent": TeamMessageSent;
        ".Workbench.App.Events.UserNotification": UserNotification;
        ".Workbench.App.Events.UserRegisteredEvent": UserRegisteredEvent;
        ".Workbench.App.Events.UserSynced": AppUserSynced;
        ".Workbench.Crm.Events.UserSynced": CrmUserSynced;
    }
}
