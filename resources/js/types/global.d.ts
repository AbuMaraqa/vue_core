import type { createHeadManager, Page, Router } from '@inertiajs/core';
import type { Directive } from 'vue';
import type { Auth } from '@/types/auth';
import type { Direction, Locale } from '@/types/localization';
import type { SidebarSection } from '@/types/sidebar';
import type { Theme } from '@/types/theme';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            theme: Theme;
            auth: Auth;
            locale: string;
            direction: Direction;
            locales: Locale[];
            translations: Record<string, string>;
            sidebar: SidebarSection[];
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface GlobalDirectives {
        vFocus: Directive<HTMLElement, boolean | undefined>;
    }

    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
