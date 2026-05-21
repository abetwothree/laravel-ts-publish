import { type AsEnum } from '@tolki/enum';

import { Status, Visibility } from '../../enums';
import type { StatusType } from '../../enums';
import type { CategoryResource, CommentResource, ImageResource, UserResource } from '.';

/** Exercises: ternary operator in various return-value positions. All properties in this resource use the ternary operator (`? :`) or the Elvis operator (`?:`) as the value expression. The scenarios cover: - enum resource vs null - enum resource vs enum resource (same / different enum class) - named resource vs null - named resource vs named resource (same / different class) - resource collection vs null - scalar vs null (string, integer) - string literal vs string literal - Elvis / short-ternary - ternary nested inside a whenLoaded closure - ternary with $this->resource accessor */
export interface TernaryResource
{
    status_or_null: AsEnum<typeof Status> | null;
    status_or_status: AsEnum<typeof Status>;
    status_resource_or_type: AsEnum<typeof Status> | StatusType;
    status_or_visibility: AsEnum<typeof Status> | AsEnum<typeof Visibility> | null;
    category_or_null: CategoryResource | null;
    category_or_category: CategoryResource;
    category_or_user: CategoryResource | UserResource;
    image_or_null: ImageResource | null;
    comments_or_null: CommentResource[] | null;
    comments_or_comments: CommentResource[];
    title_or_null: string | null;
    word_count_or_null: number | null;
    pin_label: string;
    title_fallback: string;
    category_when_loaded_or_null?: CategoryResource | null;
    category_resource_or_null: CategoryResource | null;
}
