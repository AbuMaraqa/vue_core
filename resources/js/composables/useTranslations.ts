import { usePage } from '@inertiajs/vue3';

export type TranslationReplacements = Record<string, string | number>;

export type UseTranslationsReturn = {
    t: (key: string, replacements?: TranslationReplacements) => string;
};

/**
 * Translate interface strings with the JSON translations shared by Laravel.
 *
 * Keys are the English source strings, so a missing translation falls back to
 * readable English. Placeholders use Laravel's `:name` syntax.
 */
export function useTranslations(): UseTranslationsReturn {
    const page = usePage();

    function t(
        key: string,
        replacements: TranslationReplacements = {},
    ): string {
        const translation = page.props.translations[key] ?? key;

        return Object.entries(replacements).reduce(
            (text, [name, value]) => text.replaceAll(`:${name}`, String(value)),
            translation,
        );
    }

    return { t };
}
