import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

/**
 * Show the toast the server flashes with `Inertia::flash('toast', [...])`.
 */
export function initializeFlashToast(): void {
    router.on('flash', (event) => {
        const message = event.detail.flash.toast;

        if (message) {
            toast[message.type](message.message);
        }
    });
}
