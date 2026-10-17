/**
 * A notification of the signed-in user (NotificationResource), already
 * translated by the server.
 */
export type AppNotification = {
    id: string;
    title: string;
    body: string;
    level: 'info' | 'success' | 'warning' | 'error';
    url: string | null;
    read_at: string | null;
    created_at: string | null;
};
