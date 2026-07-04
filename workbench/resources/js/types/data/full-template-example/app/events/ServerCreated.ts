import type { BroadcastableEvent } from '@/types/broadcast';

/** @see Workbench\App\Events\ServerCreated */
export interface ServerCreated extends BroadcastableEvent {
    serverId: number;
    serverName: string;
}
