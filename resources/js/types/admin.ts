export type PaginationLink = {
    url: string | null;
    label: string;
    page: number | null;
    active: boolean;
};

/**
 * A single API Resource, e.g. `UserResource::make($user)`.
 */
export type Resource<T> = {
    data: T;
};

/**
 * A paginated API Resource collection, e.g. `UserResource::collection($query->paginate())`.
 */
export type Paginated<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        links: PaginationLink[];
        path: string;
        per_page: number;
        to: number | null;
        total: number;
    };
};

/**
 * What the signed-in user may do with a record, computed by its policy.
 */
export type ResourceAbilities = {
    update: boolean;
    delete: boolean;
};

export type ManagedUser = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    roles?: string[];
    created_at: string | null;
    can: ResourceAbilities;
};

export type Role = {
    id: number;
    name: string;
    is_protected: boolean;
    is_super_admin: boolean;
    permissions?: string[];
    permissions_count?: number;
    users_count?: number;
    can: ResourceAbilities;
};

export type PermissionGroup = {
    module: string;
    permissions: string[];
};

export type Activity = {
    id: number;
    log_name: string | null;
    description: string;
    event: string | null;
    subject_type: string | null;
    subject_id: number | null;
    causer: string | null;
    changes: {
        attributes?: Record<string, unknown>;
        old?: Record<string, unknown>;
    } | null;
    created_at: string | null;
};

/**
 * What an import did (ImportResult on the server), flashed as `importResult`.
 */
export type ImportResult = {
    imported: number;
    failures: Array<{
        row: number;
        column: string;
        errors: string[];
    }>;
};

/**
 * A backup file (Backups::all() on the server).
 */
export type BackupFile = {
    id: string;
    disk: string;
    name: string;
    size: number;
    created_at: string;
};
