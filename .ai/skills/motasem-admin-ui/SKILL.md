---
name: motasem-admin-ui
description: "Frontend conventions of this project's Vue/Inertia admin panel: page structure (Index/Create/Edit + Form component), the Base* design-system components and their props, PageHeader/TablePagination/ConfirmDialog/EmptyState, Solar icons and AppIcon, Tailwind v4 tokens (brand color, th-cell, menu-item), dark mode, RTL-safe classes, Wayfinder imports, useForm, filters and delete patterns, composables and TS types. Activate whenever creating or editing any .vue, .ts or CSS file under resources/, building a page, table, form, modal or button, or when the user mentions الواجهة، صفحة، تصميم، جدول، فورم، UI, RTL or dark mode."
---

# Admin UI (Vue + Inertia + Tailwind v4)

Before writing UI, read one existing sibling page. `pages/admin/users/*` and `components/users/UserForm.vue` are the reference CRUD. The design is TailAdmin; keep its visual language.

## Hard rules

- **Single root element.** Use `<script setup lang="ts">`. Typed `defineProps<{...}>()` (use `withDefaults` for optional props) and `defineModel` for v-model.
- **Build UI from `components/base/Base*` and `components/common/*`.** Do **not** use `components/ui/*` (the shadcn primitives left there only serve the toaster, the OTP input and the 2FA setup dialog).
- **Every visible string goes through `const { t } = useTranslations()`.** Add its Arabic entry to `lang/ar.json`. Server-provided labels (role names, sidebar titles, permission names) are also passed through `t()`.
- **URLs only from Wayfinder.** Use `import { create, destroy, edit, index } from '@/routes/admin/users'` with `href="create()"` and `edit({ user: user.id })`. The `{locale}` segment is filled automatically; never pass it.
- **RTL-safe classes only.**
    - Use `ms-/me-/ps-/pe-/start-/end-/text-start/text-end`, never `ml-/mr-/pl-/pr-/left-/right-`.
    - Mirror directional icons with `rtl:rotate-180`.
- **Support dark mode on every colored element.** Use `dark:` variants: the `.dark` class is set on `<html>`.
- **Format before finishing:** `npx vp fmt` (4 spaces, single quotes, 80 columns, sorted Tailwind classes). Then `npm run types:check`.

## Layouts

`app.ts` assigns layouts automatically by page name, so pages never import a layout:

| Page name       | Layout                                                                                |
| --------------- | ------------------------------------------------------------------------------------- |
| `Welcome`       | none                                                                                  |
| `auth/*`        | `AuthLayout` (takes `title`/`description` via `defineOptions({ layout: { title } })`) |
| everything else | `AdminLayout` (sidebar + header + `<Toaster>`)                                        |

## Base components (props)

| Component                                   | API                                                                                                                           |
| ------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| `BaseButton`                                | `variant: primary\|outline\|danger\|ghost`, `size: sm\|md`, `type`, `href` (renders an Inertia `Link`), `loading`, `disabled` |
| `BaseInput` / `BaseTextarea` / `BaseSelect` | `v-model`, `invalid` (red state). Select takes `<option>` children; layout classes go to the wrapper.                         |
| `BaseCheckbox`                              | `v-model` as a boolean, or an array with `:value` for groups; `indeterminate`; label text in the slot                         |
| `BaseLabel`                                 | `for`, `required` (shows an asterisk)                                                                                         |
| `BaseInputError`                            | `message` (hidden when empty)                                                                                                 |
| `BaseCard`                                  | `title`, `description`, default slot                                                                                          |
| `BaseModal`                                 | `v-model:open`, `title`, `maxWidth: sm\|md\|lg`, `#footer` slot; closes on Escape                                             |
| `BaseBadge`                                 | `color: primary\|success\|error\|warning\|info\|light`, `size: sm\|md`                                                        |
| `BaseAlert`                                 | `variant: success\|error\|warning\|info`, `title`, `messages[]`                                                               |
| `BaseAvatar`                                | `name` (initials), `size: sm\|md\|lg`                                                                                         |
| `BaseField`                                 | `label`, `for`, `required`, `hint`, `error`; the control goes in the slot. Use it for every form field.                       |
| `BaseIconButton`                            | `label` (tooltip + aria-label), `href` (renders a Link) or `@click`, `color: neutral\|danger`; icon in the slot               |
| `BasePasswordInput`                         | a `BaseInput` with a show/hide toggle; same attrs, `invalid`, `autofocus`                                                     |

| Common component  | API                                                                                                                 |
| ----------------- | ------------------------------------------------------------------------------------------------------------------- |
| `PageHeader`      | `title` (also sets `<Head>`), `description`, `breadcrumbs: {title, href?}[]` ("Home" is prepended), `#actions` slot |
| `DataCard`        | the card around a list: `#search` and `#filters` slots, default slot for the table/grid, `:pagination="x"`          |
| `SearchInput`     | `v-model`, `placeholder`; the search field of a `DataCard`                                                          |
| `TablePagination` | `:meta="x.meta" :links="x.links"` (already included by `DataCard`)                                                  |
| `ConfirmDialog`   | `open`, `title`, `message`, `confirmLabel`, `processing`, `@confirm`, `@update:open`                                |
| `EmptyState`      | `message`, optional slot                                                                                            |
| `AppIcon`         | `name` from the server (sidebar), mapped to a Solar icon                                                            |

Feature folders: `components/<feature>/` (users, roles, media, settings, dashboard), `components/account/` (the signed-in user's own profile, passkeys and 2FA), `components/layout/` (sidebar, header, logo).

## Page patterns

**Index page** (from `users/Index.vue`):

```ts
const props = defineProps<{
    users: Paginated<ManagedUser>;
    roles: string[];
    filters: { search: string | null; role: string | null; sort: string };
}>();

// Filters live in the query string; selects apply at once, `search` after a pause.
const { filters, sortBy } = useIndexFilters(
    index,
    { search: props.filters.search, role: props.filters.role },
    { sort: props.filters.sort },
);

const deletion = useConfirmDelete((user: ManagedUser) =>
    destroy({ user: user.id }),
);
```

```vue
<DataCard :pagination="users">
    <template #search>
        <SearchInput v-model="filters.search" :placeholder="t('Search by name or email...')" />
    </template>
    <template #filters>
        <BaseSelect v-model="filters.role" class="sm:w-52">...</BaseSelect>
    </template>

    <div class="custom-scrollbar max-w-full overflow-x-auto">
        <table v-if="users.data.length" class="min-w-full">... th-cell / td-cell ...
            <BaseIconButton v-if="user.can.update" :href="edit({ user: user.id })" :label="t('Edit')"><Pencil /></BaseIconButton>
            <BaseIconButton v-if="user.can.delete" color="danger" :label="t('Delete')" @click="deletion.ask(user)"><Trash2 /></BaseIconButton>
        </table>
        <EmptyState v-else :message="t('No users found.')" />
    </div>
</DataCard>

<ConfirmDialog
    :open="deletion.target !== null"
    :title="t('Delete user')"
    :message="
        t(
            'Are you sure you want to delete :name? This action cannot be undone.',
            { name: deletion.target?.name ?? '' },
        )
    "
    :processing="deletion.processing"
    @update:open="(open) => !open && deletion.cancel()"
    @confirm="deletion.confirm"
/>
```

- The page starts with `PageHeader`, whose `#actions` slot holds the create button guarded by `can('User Create')`.
- Row edit/delete buttons render only when `item.can.update` / `item.can.delete` is true.
- `useConfirmDelete` accepts `{ onSuccess }`, e.g. to close a details panel (media library).

**Create/Edit pages** are thin: `PageHeader` with breadcrumbs, then `<XForm :x="x?.data" :options="..." />`. A single Resource arrives wrapped as `Resource<T>` (`{ data: T }`).

**Form component** (from `UserForm.vue`):

```ts
const form = useForm({
    name: props.user?.name ?? '',
    roles: props.user?.roles ?? [],
});
function submit(): void {
    form.submit(props.user ? update({ user: props.user.id }) : store(), {
        preserveScroll: true,
    });
}
```

- Layout: `grid grid-cols-12 gap-6`. Main `BaseCard` in `lg:col-span-8`, side card in `lg:col-span-4`, buttons row at `col-span-12 flex justify-end gap-3` (Cancel = outline `:href="index()"`, Submit = `type="submit" :loading="form.processing"`).
- Each field is `<BaseField :label="t('Name')" for="name" required :error="form.errors.name">`, wrapping `<BaseInput id="name" v-model="form.name" :invalid="Boolean(form.errors.name)" />`.
- Admin pages use `useForm`. Auth pages and the account security page use Inertia's `<Form>` component.

**Feedback.** Success messages come from the server: `Inertia::flash('toast', ...)` is shown by `lib/flashToast.ts`. Don't call `toast()` for server results. **Deferred props** (e.g. dashboard `registrations`) need a skeleton/pulse placeholder while loading.

## Permissions in the UI

- `const { can, canAny } = usePermissions()` reads `page.props.auth.permissions`.
- Prefer the Resource's `can` flags for per-row actions, and `can('<Permission Name>')` for page-level buttons. See **motasem-permissions**.
- Hiding is cosmetic; the server always authorizes.

## Icons

- Import Solar icons as components: `import Pencil from '~icons/solar/pen-linear'`, used as `class="size-5"`. Browse names at https://icon-sets.iconify.design/solar/ and prefer the `-linear` style.
- Sidebar icons are names sent by the server. Register new ones in the `icons` map of `components/common/AppIcon.vue`; unknown names fall back to a circle.

## Styling tokens (resources/css/app.css)

- **Brand colors:** `brand-25 … brand-950` are derived with `color-mix` from the runtime `--brand-color` variable (set from settings). Always use `brand-*` for primary accents, never a fixed blue.
- **Other palettes:** `gray-*`, `success-*`, `error-*`, `warning-*`, `blue-light-*`.
- **Typography:** `text-title-sm|md...`, `text-theme-xs|sm`. **Shadows:** `shadow-theme-xs...`.
- **Utilities:** `th-cell`, `td-cell`, `menu-item(-active|-inactive)`, `menu-dropdown-item...`, `custom-scrollbar`, `no-scrollbar`.
- **Font:** comes from `--app-font` (chosen in settings); don't set font families on components.

## Composables & lib

| Module                            | Purpose                                                                                                        |
| --------------------------------- | -------------------------------------------------------------------------------------------------------------- |
| `useTranslations`                 | `t(key, {name})` with `:name` placeholders                                                                     |
| `useLocale`                       | `locale`, `direction`, `locales`, `switchLocale(code)`                                                         |
| `usePermissions`                  | `can`, `canAny`                                                                                                |
| `useSidebar`                      | sidebar state (inside AdminLayout only)                                                                        |
| `useAppearance`                   | light/dark/system                                                                                              |
| `useBrandTheme`                   | applies the brand font/color from the settings                                                                 |
| `useIndexFilters`                 | `{ filters, sort, sortBy, apply }` for index pages (spatie query-builder query string)                         |
| `useConfirmDelete`                | `{ target, processing, ask, cancel, confirm }` for a delete confirmation dialog                                |
| `lib/media`                       | `emptyMediaInput()` — the initial value of a `MediaUpload` field                                               |
| `useTwoFactorAuth`, `useInitials` | 2FA setup data; avatar initials                                                                                |
| `lib/format`                      | `formatDate(value, locale)`, `formatNumber`, `formatRelative`; always pass `locale` from `useLocale()`         |
| `lib/activity`                    | `eventColor`                                                                                                   |
| `lib/utils`                       | `cn()`                                                                                                         |
| HTTP outside Inertia visits       | Prefer Inertia v3 `useHttp()` (as in `MediaPicker.vue`). `lib/http.ts` exists only for FilePond's raw `fetch`. |

## Types

- Shared types live in `resources/js/types/*.ts` and are re-exported by `types/index.ts`. Import from `@/types`.
- `Resource<T>` matches `XResource::make()`, and `Paginated<T>` matches a paginated Resource collection.
- Each resource type mirrors its PHP Resource field by field. Fields wrapped in `whenLoaded`/`whenCounted` are optional (`?`).
- Shared page props are typed in `types/global.d.ts`.
