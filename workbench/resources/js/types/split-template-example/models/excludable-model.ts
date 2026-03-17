import type { Post } from './';

/** Model with excluded mutator and relation via #[TsExclude]. */
export interface ExcludableModel
{
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    password: string;
    options: string | null;
    remember_token: string | null;
    created_at: string | null;
    updated_at: string | null;
    role: string | null;
    membership_level: string | null;
    phone: string | null;
    avatar: string | null;
    bio: string | null;
    settings: string | null;
    last_login_at: string | null;
    last_login_ip: string | null;
}

export interface ExcludableModelMutators
{
    /** Included mutator — should appear in TS output */
    display_name: string;
}

export interface ExcludableModelRelations
{
    // Relations
    /** Included relation — should appear in TS output */
    posts: Post[];
    // Counts
    posts_count: number;
    // Exists
    posts_exists: boolean;
}

export interface ExcludableModelAll extends ExcludableModel, ExcludableModelMutators, ExcludableModelRelations {}
