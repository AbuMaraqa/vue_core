/**
 * A browser or app identified from its user agent.
 */
export type Device = {
    browser: string | null;
    platform: string | null;
    is_mobile: boolean;
};

/**
 * An entry of the login history (AuthenticationLogResource).
 */
export type AuthenticationLog = {
    id: number;
    event: 'login' | 'logout' | 'failed' | 'lockout';
    event_label: string;
    event_color: 'success' | 'light' | 'warning' | 'error';
    email: string | null;
    user?: { id: number; name: string } | null;
    ip_address: string | null;
    device: Device;
    created_at: string;
};

/**
 * One of the signed-in user's browser sessions (UserSessions::for()).
 */
export type BrowserSession = {
    key: string;
    ip_address: string | null;
    device: Device;
    last_active_at: string;
    is_current: boolean;
};
