import { router } from '@inertiajs/vue3';
import type { Theme } from '@/types';

/**
 * Apply the store font and primary color to the document.
 *
 * The server renders the same values on the first visit, so this only runs
 * when they change, e.g. after saving the settings or while previewing them.
 */
export function applyTheme(
    theme: Pick<Theme, 'fontVariable' | 'primaryColor'>,
): void {
    const style = document.documentElement.style;

    style.setProperty('--app-font', `var(${theme.fontVariable})`);
    style.setProperty('--brand-color', theme.primaryColor);
}

export function initializeStoreTheme(): void {
    router.on('navigate', (event) => {
        applyTheme(event.detail.page.props.theme);
    });
}
