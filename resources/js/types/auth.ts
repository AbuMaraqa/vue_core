/**
 * The signed-in user shared with every page by HandleInertiaRequests::sharedUser().
 */
export type User = {
    id: number;
    name: string;
    email: string;
    locale: string | null;
    email_verified_at: string | null;
};

export type Auth = {
    user: User | null;
    permissions: string[];
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
