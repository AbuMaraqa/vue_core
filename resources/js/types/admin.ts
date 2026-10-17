export type PaginationLink = {
    url: string | null;
    label: string;
    page: number | null;
    active: boolean;
};

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

export type ResourceAbilities = {
    update: boolean;
    delete: boolean;
};

export type ManagedUser = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    roles: string[];
    created_at: string;
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
    key: string;
    label: string;
    permissions: Array<{ name: string; label: string }>;
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
    created_at: string;
};
