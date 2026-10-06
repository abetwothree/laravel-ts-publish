import type { TeamResource } from '../http/resources';

/** @see Workbench\App\Events\TeamRosterSynced */
export interface TeamRosterSynced {
    team: TeamResource;
}
