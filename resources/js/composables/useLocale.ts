import { router, usePage } from '@inertiajs/vue3';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';
import LocaleController from '@/actions/App/Modules/Core/Http/Controllers/LocaleController';
import type { Direction, Locale } from '@/types';

export type UseLocaleReturn = {
    locale: ComputedRef<string>;
    direction: ComputedRef<Direction>;
    locales: ComputedRef<Locale[]>;
    currentLocale: ComputedRef<Locale | undefined>;
    switchLocale: (code: string) => void;
};

/**
 * Keep the `lang` and `dir` attributes of the document in sync with the server locale.
 */
export function initializeLocale(): void {
    router.on('navigate', (event) => {
        const { locale, direction } = event.detail.page.props;

        document.documentElement.lang = locale;
        document.documentElement.dir = direction;
    });
}

export function useLocale(): UseLocaleReturn {
    const page = usePage();

    const locale = computed(() => page.props.locale);
    const direction = computed(() => page.props.direction);
    const locales = computed(() => page.props.locales);
    const currentLocale = computed(() =>
        locales.value.find((item) => item.code === locale.value),
    );

    function switchLocale(code: string): void {
        if (code === locale.value) {
            return;
        }

        router.visit(LocaleController(), {
            data: { locale: code },
            preserveScroll: true,
            preserveState: false,
        });
    }

    return { locale, direction, locales, currentLocale, switchLocale };
}
