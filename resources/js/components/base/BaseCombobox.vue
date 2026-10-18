<script setup lang="ts">
/**
 * A select with a search box, for long lists such as accounts, contacts and
 * items. Options are filtered in the browser, or fetched with `search` for
 * lists too large to send with the page. With `search`, pass the current
 * selection in `options` so its label shows before the first search.
 */
import ChevronDown from '~icons/solar/alt-arrow-down-linear';
import CloseCircle from '~icons/solar/close-circle-linear';
import { onClickOutside, useEventListener, watchDebounced } from '@vueuse/core';
import type { CSSProperties } from 'vue';
import { computed, nextTick, ref, useTemplateRef } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import type { ComboboxOption } from '@/types';

const props = withDefaults(
    defineProps<{
        options?: ComboboxOption[];
        search?: (query: string) => Promise<ComboboxOption[]>;
        placeholder?: string;
        invalid?: boolean;
        disabled?: boolean;
        clearable?: boolean;
        id?: string;
    }>(),
    {
        options: () => [],
        search: undefined,
        placeholder: undefined,
        invalid: false,
        disabled: false,
        clearable: false,
        id: undefined,
    },
);

const model = defineModel<string | number | null>({ default: null });

const emit = defineEmits<{
    select: [option: ComboboxOption | null];
}>();

const { t } = useTranslations();

const isOpen = ref(false);
const query = ref('');
const highlighted = ref(0);
const loading = ref(false);
const remoteOptions = ref<ComboboxOption[]>([]);
const lastChosen = ref<ComboboxOption | null>(null);
const container = useTemplateRef('container');
const trigger = useTemplateRef<HTMLButtonElement>('trigger');
const panel = useTemplateRef<HTMLDivElement>('panel');
const searchInput = useTemplateRef<HTMLInputElement>('searchInput');
const panelStyle = ref<CSSProperties>({});

/** Roughly the panel's full height: search box plus a max-h-64 list. */
const PANEL_HEIGHT = 320;
const PANEL_GAP = 4;

const filtered = computed<ComboboxOption[]>(() => {
    if (props.search) {
        return remoteOptions.value;
    }

    const term = query.value.trim().toLowerCase();

    return term === ''
        ? props.options
        : props.options.filter((option) =>
              `${option.label} ${option.hint ?? ''}`
                  .toLowerCase()
                  .includes(term),
          );
});

const selected = computed<ComboboxOption | null>(
    () =>
        props.options.find((option) => option.value === model.value) ??
        (lastChosen.value?.value === model.value ? lastChosen.value : null),
);

onClickOutside(container, () => (isOpen.value = false), { ignore: [panel] });

/**
 * The panel is teleported to <body> so scrolling cards, tables and modals
 * cannot clip it; it is placed against the trigger with fixed positioning,
 * opening upwards when there is not enough room below.
 */
function position(): void {
    const rect = trigger.value?.getBoundingClientRect();

    if (!rect) {
        return;
    }

    const viewportWidth = document.documentElement.clientWidth;
    const spaceBelow = window.innerHeight - rect.bottom;
    const opensUp = spaceBelow < PANEL_HEIGHT && rect.top > spaceBelow;
    const isRtl = getComputedStyle(trigger.value!).direction === 'rtl';

    panelStyle.value = {
        width: `max(${rect.width}px, 16rem)`,
        ...(opensUp
            ? { bottom: `${window.innerHeight - rect.top + PANEL_GAP}px` }
            : { top: `${rect.bottom + PANEL_GAP}px` }),
        ...(isRtl
            ? { right: `${viewportWidth - rect.right}px` }
            : { left: `${rect.left}px` }),
    };
}

useEventListener(window, 'resize', () => isOpen.value && position());
useEventListener(window, 'scroll', () => isOpen.value && position(), {
    capture: true,
    passive: true,
});

/**
 * Each search is numbered so a slow response cannot overwrite the results of
 * a later one; the term of the latest search lets the debounced watcher skip
 * a term that `open()` already searched for.
 */
let latestRequest = 0;
let requestedTerm: string | null = null;

async function load(): Promise<void> {
    if (!props.search) {
        return;
    }

    const request = ++latestRequest;
    const term = query.value.trim();
    requestedTerm = term;
    loading.value = true;

    try {
        const results = await props.search(term);

        if (request === latestRequest) {
            remoteOptions.value = results;
            highlighted.value = 0;
        }
    } finally {
        if (request === latestRequest) {
            loading.value = false;
        }
    }
}

watchDebounced(
    query,
    () => {
        if (query.value.trim() !== requestedTerm) {
            void load();
        }
    },
    { debounce: 250 },
);

async function open(): Promise<void> {
    if (props.disabled) {
        return;
    }

    position();
    isOpen.value = true;
    query.value = '';
    highlighted.value = Math.max(
        filtered.value.findIndex((option) => option.value === model.value),
        0,
    );

    await nextTick();
    searchInput.value?.focus();

    if (
        props.search &&
        (remoteOptions.value.length === 0 || requestedTerm !== '')
    ) {
        await load();
    }
}

function choose(option: ComboboxOption): void {
    if (option.disabled) {
        return;
    }

    model.value = option.value;
    lastChosen.value = option;
    emit('select', option);
    isOpen.value = false;
}

function clear(): void {
    model.value = null;
    lastChosen.value = null;
    emit('select', null);
}

function move(step: number): void {
    const count = filtered.value.length;

    if (count > 0) {
        highlighted.value = (highlighted.value + step + count) % count;
    }
}

function chooseHighlighted(): void {
    const option = filtered.value[highlighted.value];

    if (option) {
        choose(option);
    }
}
</script>

<template>
    <div ref="container" class="relative">
        <button
            :id="id"
            ref="trigger"
            type="button"
            :disabled="disabled"
            :class="[
                'flex h-11 w-full items-center gap-2 rounded-lg border bg-transparent px-4 text-start text-sm shadow-theme-xs focus:ring-3 focus:outline-hidden disabled:cursor-not-allowed disabled:opacity-60 dark:bg-gray-900',
                invalid
                    ? 'border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700'
                    : 'border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:focus:border-brand-800',
            ]"
            @click="isOpen ? (isOpen = false) : open()"
        >
            <span
                :class="[
                    'min-w-0 flex-1 truncate',
                    selected
                        ? 'text-gray-800 dark:text-white/90'
                        : 'text-gray-400 dark:text-white/30',
                ]"
            >
                {{ selected?.label ?? placeholder ?? t('Select...') }}
            </span>
            <CloseCircle
                v-if="clearable && selected && !disabled"
                class="size-4 shrink-0 text-gray-400 hover:text-gray-600"
                @click.stop="clear"
            />
            <ChevronDown class="size-4 shrink-0 text-gray-500" />
        </button>

        <Teleport to="body">
            <div
                v-if="isOpen"
                ref="panel"
                :style="panelStyle"
                class="fixed z-100000 rounded-lg border border-gray-200 bg-white p-2 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900"
            >
                <input
                    ref="searchInput"
                    v-model="query"
                    type="search"
                    :placeholder="t('Search...')"
                    class="mb-2 h-9 w-full rounded-md border border-gray-200 bg-transparent px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden dark:border-gray-700 dark:text-white/90"
                    @keydown.down.prevent="move(1)"
                    @keydown.up.prevent="move(-1)"
                    @keydown.enter.prevent="chooseHighlighted"
                    @keydown.esc.prevent="isOpen = false"
                />

                <ul class="custom-scrollbar max-h-64 overflow-y-auto">
                    <li
                        v-for="(option, position) in filtered"
                        :key="option.value"
                        :class="[
                            'cursor-pointer rounded-md px-3 py-2 text-sm',
                            option.disabled
                                ? 'cursor-not-allowed opacity-50'
                                : position === highlighted
                                  ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400'
                                  : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5',
                            { 'font-semibold': option.value === model },
                        ]"
                        @mouseenter="highlighted = position"
                        @click="choose(option)"
                    >
                        {{ option.label }}
                        <span
                            v-if="option.hint"
                            class="block text-theme-xs text-gray-400"
                        >
                            {{ option.hint }}
                        </span>
                    </li>
                    <li
                        v-if="!filtered.length"
                        class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400"
                    >
                        {{ loading ? t('Loading...') : t('No results.') }}
                    </li>
                </ul>

                <!--
                    Extra actions under the list, e.g. "+ New customer"; gets
                    the typed search and a `close` function for the panel.
                -->
                <slot
                    name="footer"
                    :query="query.trim()"
                    :close="() => (isOpen = false)"
                />
            </div>
        </Teleport>
    </div>
</template>
