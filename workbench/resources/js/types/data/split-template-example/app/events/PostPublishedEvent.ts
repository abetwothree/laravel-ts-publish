import type { Post } from '../models';

/** @see Workbench\App\Events\PostPublishedEvent */
export interface PostPublishedEvent {
    post: Partial<Post>;
    message: string;
}
