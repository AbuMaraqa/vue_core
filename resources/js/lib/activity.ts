export type ActivityColor = 'success' | 'info' | 'error' | 'light';

/**
 * Map an activity log event to the badge color used to display it.
 */
export function eventColor(event: string | null): ActivityColor {
    switch (event) {
        case 'created':
            return 'success';
        case 'updated':
            return 'info';
        case 'deleted':
            return 'error';
        default:
            return 'light';
    }
}
