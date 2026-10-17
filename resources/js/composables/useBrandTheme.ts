import { router } from '@inertiajs/vue3';
import type { Theme } from '@/types';

/**
 * Apply the brand font and primary color chosen in the settings to the document.
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

export function initializeBrandTheme(): void {
    router.on('navigate', (event) => {
        applyTheme(event.detail.page.props.theme);
    });
}
