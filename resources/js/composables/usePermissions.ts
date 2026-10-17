import { usePage } from '@inertiajs/vue3';

export type UsePermissionsReturn = {
    can: (permission: string) => boolean;
    canAny: (...permissions: string[]) => boolean;
};

/**
 * Check the permissions of the signed-in user to toggle interface elements.
 *
 * This only hides controls; every action is still authorized on the server.
 */
export function usePermissions(): UsePermissionsReturn {
    const page = usePage();

    const can = (permission: string): boolean =>
        page.props.auth.permissions.includes(permission);

    const canAny = (...permissions: string[]): boolean =>
        permissions.some((permission) => can(permission));

    return { can, canAny };
}
