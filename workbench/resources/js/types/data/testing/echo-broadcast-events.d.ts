import type { ComputedNameEvent } from './workbench/app/events/ComputedNameEvent';
import type { DeclaredPropsEvent } from './workbench/app/events/DeclaredPropsEvent';
import type { EnumBroadcastEvent } from './workbench/app/events/EnumBroadcastEvent';
import type { MixedTypesEvent } from './workbench/app/events/MixedTypesEvent';
import type { MultiModelEvent } from './workbench/app/events/MultiModelEvent';
import type { OrderShipped } from './workbench/app/events/OrderShipped';
import type { PayloadDiffersEvent } from './workbench/app/events/PayloadDiffersEvent';
import type { PostPublishedEvent } from './workbench/app/events/PostPublishedEvent';
import type { PureEnumEvent } from './workbench/app/events/PureEnumEvent';
import type { ReportSynced } from './workbench/app/events/ReportSynced';
import type { ServerCreated } from './workbench/app/events/ServerCreated';
import type { StatusSynced } from './workbench/crm/events/StatusSynced';
import type { TeamMessageSent } from './workbench/app/events/TeamMessageSent';
import type { UserNotification } from './workbench/app/events/UserNotification';
import type { UserRegisteredEvent } from './workbench/app/events/UserRegisteredEvent';
import type { UserSynced as AppUserSynced } from './workbench/app/events/UserSynced';
import type { UserSynced as CrmUserSynced } from './workbench/crm/events/UserSynced';

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
