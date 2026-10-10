import type { ComputedNameEvent } from './workbench/app/events/ComputedNameEvent';
import type { DeclaredPropsEvent } from './workbench/app/events/DeclaredPropsEvent';
import type { DispatchRelayed } from './workbench/app/events/DispatchRelayed';
import type { DocblockShapedEvent } from './workbench/app/events/DocblockShapedEvent';
import type { EnumBroadcastEvent } from './workbench/app/events/EnumBroadcastEvent';
import type { FacilityAudited } from './workbench/app/events/FacilityAudited';
import type { ManifestAssembled } from './workbench/app/events/ManifestAssembled';
import type { MixedTypesEvent } from './workbench/app/events/MixedTypesEvent';
import type { MultiModelEvent } from './workbench/app/events/MultiModelEvent';
import type { OrderShipped } from './workbench/app/events/OrderShipped';
import type { PayloadDiffersEvent } from './workbench/app/events/PayloadDiffersEvent';
import type { PostPublishedEvent } from './workbench/app/events/PostPublishedEvent';
import type { PureEnumEvent } from './workbench/app/events/PureEnumEvent';
import type { ReportSynced } from './workbench/app/events/ReportSynced';
import type { ReviewerCastEvent } from './workbench/app/events/ReviewerCastEvent';
import type { SameBasenameModelEvent } from './workbench/app/events/SameBasenameModelEvent';
import type { ServerCreated } from './workbench/app/events/ServerCreated';
import type { StatusSynced } from './workbench/crm/events/StatusSynced';
import type { TaggedPayloadEvent } from './workbench/app/events/TaggedPayloadEvent';
import type { TeamMessageSent } from './workbench/app/events/TeamMessageSent';
import type { TeamRosterSynced } from './workbench/app/events/TeamRosterSynced';
import type { UserNotification } from './workbench/app/events/UserNotification';
import type { UserRegisteredEvent } from './workbench/app/events/UserRegisteredEvent';
import type { UserSynced as AppUserSynced } from './workbench/app/events/UserSynced';
import type { UserSynced as CrmUserSynced } from './workbench/crm/events/UserSynced';

declare module "@laravel/echo" {
    interface Events {
        ".Workbench.App.Events.ComputedNameEvent": ComputedNameEvent;
        ".Workbench.App.Events.DeclaredPropsEvent": DeclaredPropsEvent;
        ".Workbench.App.Events.DispatchRelayed": DispatchRelayed;
        ".Workbench.App.Events.DocblockShapedEvent": DocblockShapedEvent;
        ".Workbench.App.Events.EnumBroadcastEvent": EnumBroadcastEvent;
        ".Workbench.App.Events.FacilityAudited": FacilityAudited;
        ".Workbench.App.Events.ManifestAssembled": ManifestAssembled;
        ".Workbench.App.Events.MixedTypesEvent": MixedTypesEvent;
        ".Workbench.App.Events.MultiModelEvent": MultiModelEvent;
        ".Workbench.App.Events.OrderShipped": OrderShipped;
        ".Workbench.App.Events.PayloadDiffersEvent": PayloadDiffersEvent;
        ".Workbench.App.Events.PostPublishedEvent": PostPublishedEvent;
        ".Workbench.App.Events.PureEnumEvent": PureEnumEvent;
        ".Workbench.App.Events.ReportSynced": ReportSynced;
        ".Workbench.App.Events.ReviewerCastEvent": ReviewerCastEvent;
        ".Workbench.App.Events.SameBasenameModelEvent": SameBasenameModelEvent;
        "server.created": ServerCreated;
        ".Workbench.Crm.Events.StatusSynced": StatusSynced;
        ".Workbench.App.Events.TaggedPayloadEvent": TaggedPayloadEvent;
        ".Workbench.App.Events.TeamMessageSent": TeamMessageSent;
        ".Workbench.App.Events.TeamRosterSynced": TeamRosterSynced;
        ".Workbench.App.Events.UserNotification": UserNotification;
        ".Workbench.App.Events.UserRegisteredEvent": UserRegisteredEvent;
        ".Workbench.App.Events.UserSynced": AppUserSynced;
        ".Workbench.Crm.Events.UserSynced": CrmUserSynced;
    }
}
