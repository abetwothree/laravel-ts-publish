/**
 * A `$this->resource instanceof <Model>` ternary narrows the backing model for its true arm, so a
 * relation only the subclass declares resolves there.
 *
 * @see Workbench\App\Http\Resources\TeamSubscriberResource
 */
export interface TeamSubscriberResource
{
    subscriber_name: string | null;
}
