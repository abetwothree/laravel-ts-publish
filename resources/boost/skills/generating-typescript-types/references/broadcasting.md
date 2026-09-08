# Broadcasting

Two independent phases. **Channels** compile every name registered in `routes/channels.php` into
`broadcast-channels.ts`: a `BroadcastChannel` union and a `BroadcastChannels` accessor tree, so the
frontend never spells `teams.${id}` by hand. **Events** turn every `ShouldBroadcast` /
`ShouldBroadcastNow` class into an interface, an index of event names, and (optionally) a Laravel Echo
module augmentation so `.listen()` / `useEcho()` payloads are typed.

**Gates (check both):** `config('ts-publish.broadcast_channels.enabled')` and
`config('ts-publish.broadcast_events.enabled')`, plus `broadcast_events.echo_augmentation.enabled` for
the `.d.ts`. Apps often enable one and not the other. If events are off, there is no `TaskCompleted`
interface and no augmentation: type the payload by hand at the listener (a small local interface is
correct here) and say that enabling the phase would generate it. Channels need `routes/channels.php` to be
registered (`withBroadcasting()` in `bootstrap/app.php` or `install:broadcasting`).

## Channels

```php
// routes/channels.php
Broadcast::channel('teams.{teamId}', fn (User $user, int $teamId) => $user->belongsToTeam($teamId));
Broadcast::channel('teams.{teamId}.tasks', TeamTasksChannel::class);     // class-based works the same
Broadcast::channel('public-announcements', fn () => true);
```

```ts
export type BroadcastChannel =
    | `teams.${string | number}`
    | `teams.${string | number}.tasks`
    | `public-announcements`;

export const BroadcastChannels = {
    teams: (teamId: string | number) => ({
        $channel: `teams.${teamId}` as const,          // the parent channel itself
        tasks: `teams.${teamId}.tasks` as const,
    }),
    'public-announcements': `public-announcements` as const,
};
```

```ts
import { BroadcastChannels } from '@data/broadcast-channels';
import type { BroadcastChannel } from '@data/broadcast-channels';

Echo.private(BroadcastChannels.teams(team.id).$channel);   // 'teams.42'
Echo.private(BroadcastChannels.teams(team.id).tasks);      // 'teams.42.tasks'
Echo.channel(BroadcastChannels['public-announcements']);

function subscribe(channel: BroadcastChannel) { ... }       // accepts only registered names
```

- A name with no `{param}` is a string constant; a trailing `{param}` makes a function; a `{param}` with
  children makes a function returning an object, with `$channel` for the parent when it is also a channel.
- Every `{param}` is `string | number`; the model/enum bound on the PHP side does not change that.
- Hyphenated segments are quoted keys. There are no attributes, no filtering and no barrel: the whole
  file is regenerated from the registered names. Laravel's `private-`/`presence-` prefixes are added by
  Echo, not by these names.

## Events

```php
class TaskCompleted implements ShouldBroadcast
{
    public function __construct(public Task $task) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("teams.{$this->task->team_id}");
    }

    public function broadcastAs(): string
    {
        return 'task.completed';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->task->id,
            'title' => $this->task->title,
            'completed_at' => $this->task->completed_at,   // the cast types it; Carbon JSON-encodes to ISO-8601
        ];
    }
}
```

```ts
// app/events/TaskCompleted.ts  (event files keep the PHP class name, not kebab-case)
/** @see App\Events\TaskCompleted */
export interface TaskCompleted { id: number; title: string; completed_at: string | null; }

// broadcast-events.ts
export type BroadcastEvent = 'task.completed' | 'order.shipped';
export const BroadcastEvents = Object.freeze({ TaskCompleted: 'task.completed', OrderShipped: 'order.shipped' } as const);
export type { TaskCompleted, OrderShipped };

// echo-broadcast-events.d.ts
declare module '@laravel/echo-vue' {            // or -react / -svelte / @laravel/echo, auto-detected from package.json
    interface Events { 'task.completed': TaskCompleted; 'order.shipped': OrderShipped; }
}
```

- With `broadcastWith()` present it is the only payload source; otherwise every public property is used
  (promoted or class-body, `@var` docblock preferred). A model-typed property is `Partial<Model>`, an
  enum-typed one is `{Enum}Type`, both imported.
- Inside `broadcastWith()`, hand the model attribute through (`'completed_at' => $this->task->completed_at`)
  and it types from the cast (`string | null`; Carbon serializes to ISO-8601 in the JSON payload anyway).
  A call on the value (`?->toJSON()`, `->format(...)`, `->toISOString()`) types as `unknown`, and a
  `@return array{...}` docblock on `broadcastWith()` is **not** consulted as an override; fix it with
  `#[TsCasts(['completed_at' => 'string | null'])]` on the event class or by passing the attribute through.
- A class-body property with no default renders optional (`label?: string`); promote it or give it a
  default to make it required.
- **Give every broadcast event a `broadcastAs()` that returns one string literal** (`'task.completed'`),
  and **listen with a leading dot**. Laravel Echo's `EventFormatter` treats a name without a leading dot as
  relative: it prepends its `namespace` (`App.Events` by default) and turns every dot into a backslash, so
  `.listen('task.completed')` binds `App\Events\task\completed` and never fires; `.listen('.task.completed')`
  binds `task.completed`, which is the wire name. The generated `BroadcastEvents.TaskCompleted` is the bare
  `broadcastAs()` literal, so prefix it: `` `.${BroadcastEvents.TaskCompleted}` ``. Without `broadcastAs()`
  the generated key is `'.App.Events.TaskCompleted'`, which Echo strips to `App.Events.TaskCompleted`
  (dots kept) while the server broadcasts `App\Events\TaskCompleted`; for such an event listen with the
  bare class name (`'TaskCompleted'`, namespaced by Echo) or `'.App\\Events\\TaskCompleted'`.
  Because the `Events` augmentation is keyed by the generated string, not the dotted one you pass to
  `listen()`, give `useEcho`/`listen` the payload type explicitly (`useEcho<TaskCompleted>(...)`).
- Same-named events in different namespaces are aliased (`AppUserSynced` / `CrmUserSynced`).
- `#[TsCasts]`, `#[TsExtends]` / `ts_extends.broadcast_events`, and `#[TsExclude]` work as elsewhere.

### Listening on the frontend

```ts
import { useEcho } from '@laravel/echo-vue';                // or @laravel/echo-react
import { BroadcastChannels } from '@data/broadcast-channels';
import { BroadcastEvents } from '@data/broadcast-events';
import type { TaskCompleted } from '@data/app/events/TaskCompleted';

// Leading dot = "this is the exact wire name"; the explicit generic types the payload.
useEcho<TaskCompleted>(BroadcastChannels.teams(props.team.id).$channel, `.${BroadcastEvents.TaskCompleted}`, (event) => {
    const row = tasks.value.find((t) => t.id === event.id);
    if (row) row.completed_at = event.completed_at;
});

// Plain Echo
window.Echo.private(BroadcastChannels.teams(team.id).$channel)
    .listen(`.${BroadcastEvents.TaskCompleted}`, (event: TaskCompleted) => { /* ... */ });
```

Use `BroadcastEvents.X` (dot-prefixed) for the name, `BroadcastChannels` for the channel, and the generated
interface for the payload; never a hand-typed `'task.completed'` or `` `teams.${id}` `` literal.
Presence channels (`Echo.join()`) use the same channel names.

## Republishing

Channels and the events index are whole-file outputs; run `php artisan ts:publish` (or
`--only-broadcast-channels` / `--only-broadcast-events`). A single event file also refreshes with
`--source="App\Events\TaskCompleted"`.

## Common mistakes

- Writing `` `teams.${teamId}` `` or `'task.completed'` as string literals on the frontend.
- Listening with `BroadcastEvents.X` bare (no leading dot): Echo namespaces it and the listener never fires.
- Importing `@data/app/events/...` or `@data/broadcast-events` when `broadcast_events.enabled` is `false`.
- Relying on Echo payload typing without checking that `echo_augmentation.enabled` is on and the
  `.d.ts` is inside the `tsconfig` `include`.
- Adding `#[TsExclude]` to a channel; channels have no attributes, remove the registration instead.
