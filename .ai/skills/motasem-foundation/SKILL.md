---
name: motasem-foundation
description: 'The Foundation module (app/Modules/Foundation) of this project and how to use its services: make:module generator, error pages and request ids, /health, security headers, login history, active sessions, notifications (SystemNotification, Recipients), Excel/CSV/PDF exports (Exporter), spreadsheet imports (Importer), backups, the versioned JSON API with Sanctum tokens and Swagger, and shared building blocks (SearchFilter, EnumOptions, UserAgent). Activate whenever generating a module, sending a notification, adding an export/import, adding an API endpoint, touching errors, logging, security, sessions or backups, or when the user mentions إشعارات، تصدير، استيراد، Excel، PDF، API، نسخ احتياطي، سجل الدخول، الجلسات، الأخطاء.'
---

# Foundation Module

`app/Modules/Foundation` holds the cross-cutting services every module relies on.

**Dependency rule:** Foundation builds on Core's module system (`ModuleServiceProvider`, `Sidebar`, permissions), and business modules may use both. **Core never depends on Foundation.** That is why the users export/import lives in Foundation (`UserTransferController`) and not in Core.

- **Settings:** `config/foundation.php`, with env vars documented in `.env.example`.
- **Provider:** `FoundationServiceProvider`. It registers the global middleware, the error handling, the event listeners, the scheduled pruning, the rate limiters and the `make:module` command.

## 1. Generate a module (start here for new features)

```bash
php artisan make:module Catalog --resource=Category
```

This creates a working CRUD module:

- provider, routes and sidebar entry;
- model, migration and factory;
- policy, plus 5 permissions appended to PermissionSeeder (`Category View List|Create|Update|Delete|Export`);
- Store/Update requests, Data, a Save action, the resource and the controller;
- an Excel/CSV/PDF exporter;
- Vue Index/Create/Edit pages and a form component;
- the TypeScript type;
- Pest tests.

It also:

- registers the provider in `bootstrap/providers.php`;
- runs Pint, Wayfinder and the formatter;
- lists the Arabic translations still missing.

Then run `php artisan migrate`, `php artisan db:seed --class=PermissionSeeder` and the module tests, and add the translations.

How it works:

- Stubs live in `Console/stubs/module/*.stub` and use `%%Placeholder%%` tokens.
- The logic is in `Support/ModuleGenerator/ModuleGenerator.php`; `MakeModuleCommand` is a thin wrapper.
- Change the stubs to change what every future module looks like, and keep `ModuleGeneratorTest` passing.
- It refuses to overwrite an existing module. For a second resource in an existing module, follow **motasem-module-development** by hand.

## 2. Errors and observability

| Concern     | How it works                                                                                                                                                                                                                                                                                                                                                             |
| ----------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Request id  | `AssignRequestId` (global, first) gives every request an id. It is in `Context` (so it reaches every log line and queued job), the `X-Request-Id` response header, JSON error bodies (`request_id`) and the 500 page ("Reference"). A safe incoming `X-Request-Id` from a load balancer is kept.                                                                         |
| Error pages | `ExceptionResponder` is registered with `Inertia::handleExceptionsUsing`. 403/404/429/500/503 render `pages/errors/Error.vue` (no layout, translated). In debug mode a 500 still shows the developer exception page. 419 sends the user back with a toast. If the error page itself fails (e.g. database down), the plain response is returned and the failure reported. |
| API errors  | Laravel's JSON shape `{message, errors?}` plus `request_id`.                                                                                                                                                                                                                                                                                                             |
| Health      | `GET /health` (no session) checks database, cache and storage and returns 200 or 503 with per-check status and duration. Errors are logged, never returned. Laravel's `/up` still exists.                                                                                                                                                                                |

## 3. Security

| Concern         | How it works                                                                                                                                                                                                                                                                                                                                                        |
| --------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Headers         | `AddSecurityHeaders` (global) sends the headers in `foundation.security.headers`. HSTS is sent only over HTTPS and only when `SECURITY_HSTS=true`.                                                                                                                                                                                                                  |
| Login history   | `RecordAuthenticationEvents` subscribes to Laravel's `Login`/`Logout`/`Failed`/`Lockout` events, so password, 2FA, passkey and API sign-ins are all recorded in `authentication_logs`. The admin page is `admin.login-history.*` (permission `Login History View List`, export `Login History Export`). Rows are pruned daily after `LOGIN_HISTORY_RETENTION_DAYS`. |
| Active sessions | `UserSessions` reads the database session store. `settings/sessions` (behind password confirmation) lists the user's sessions and signs out one or all others. Session ids never reach the browser; each session is identified by an HMAC key. It needs `SESSION_DRIVER=database`; otherwise the page explains why it is empty.                                     |

## 4. Notifications

Extend `Notifications/SystemNotification`:

```php
class OrderShipped extends SystemNotification
{
    public function __construct(protected Order $order) {}

    protected function title(): string { return 'Order shipped'; }                       // English translation key
    protected function body(): string { return 'Order #:number is on its way.'; }      // key with :placeholders
    protected function parameters(): array { return ['number' => $this->order->number]; }
    protected function route(): ?array { return ['admin.orders.edit', ['order' => $this->order->id]]; }
    protected function level(): NotificationLevel { return NotificationLevel::Success; }
}

Notification::send(Recipients::withPermission('Order View List'), new OrderShipped($order));
```

- **Storage and translation:** notifications are queued (`ShouldQueue`) and stored in the database. Title and body are translated when read, so each user sees their own language. The link is generated from the route name in the reader's locale.
- **Recipients:** `Recipients::withPermission()` returns users holding the permission through a role or directly, plus every super admin.
- **Where they show up:**
    - the web bell (`components/layout/NotificationBell.vue`), using the shared prop `unreadNotifications` and `notifications.latest`;
    - the page `notifications.index`;
    - the API at `/api/v1/notifications`.
- **Built-in notifications:** `NewUserRegistered` (on `Registered`, to `User View List` holders), `BackupSucceeded` and `BackupFailed`.
- Add every title and body key to `lang/ar.json`.

## 5. Exports (Excel, CSV, PDF)

```php
/** @extends Exporter<Category> */
class CategoriesExporter extends Exporter
{
    public function title(): string { return 'Categories'; }
    public function columns(): array
    {
        return ['Name' => fn (Category $c): string => $c->name];            // labels are translation keys
    }
}

// controller: reuse the index QueryBuilder so the file matches the screen
public function export(ExportRequest $request): Response
{
    return CategoriesExporter::from($this->query())->download($request->exportFormat());
}
```

- **Route:** `Route::get('categories/export', ...)->can('Category Export')`. The permission is on the **route**, so it is checked before `ExportRequest` validates `format`.
- **Vue:** `<ExportMenu v-if="can('Category Export')" :route="exportMethod" />`. `exportMethod` is Wayfinder's name for an `export` route; the menu keeps the current filters through `mergeQuery`.
- **Formats:**
    - spreadsheets stream the query in chunks, with RTL sheets in Arabic;
    - PDFs are a `TableDocument` printed through the shared print layout (section 5b), capped at `foundation.exports.pdf_max_rows`.

## 5b. Printing (every PDF in the system)

Every PDF uses **one layout**, `foundation::print.layout`. The layout puts these on every page:

- **Header:** the logo, the site name and its description, then the document title, number and status badge, over a brand-colored rule.
- **Footer:** the letterhead details (phone, email, address, tax number), the footer note, "Page X of Y" and "Printed on … by …".

The **content** goes in between. The PDF uses the interface font chosen in the settings: TTF copies live in `Foundation/resources/fonts` and are registered by `PrintFonts`. Arabic shaping is on, and the page direction follows the locale.

Never build an mpdf instance or a full HTML page yourself. Write a document class instead:

```php
// app/Modules/<M>/Printing/ReceiptDocument.php
class ReceiptDocument extends FinancialDocument            // or PrintDocument for non-financial documents
{
    public function __construct(protected Payment $payment) { $payment->loadMissing([...]); }

    public function title(): string { return __('Receipt'); }                 // translated
    public function number(): ?string { return $this->payment->number; }
    public function status(): ?array { return $this->documentStatus($this->payment->status); } // Draft/Cancelled badge
    public function details(): array { return [__('Date') => self::date($this->payment->date)]; } // empty values are skipped
    public function signatures(): array { return [__('Received by'), __('Accountant')]; }

    protected function view(): string { return '<module>::print.receipt'; }  // content only
    protected function data(): array { return ['payment' => $this->payment]; }
}

// controller: same authorization as the Show page
public function pdf(Payment $payment): ReceiptDocument
{
    Gate::authorize('view', $payment);

    return new ReceiptDocument($payment);   // Responsable: inline PDF, or a download with ?download=1
}
// route: Route::get('{payment}/pdf', 'pdf')->name('pdf');
// Vue Show page: <PrintButton :url="pdf.url({ payment: payment.id })" />
```

- **Content views** receive `$direction`, `$start` and `$end` (`left`/`right`).
- **Layout CSS classes:**
    - `grid` is the lines table (`th`/`td`, with `tfoot` for totals);
    - `num` is for amounts (end-aligned, LTR);
    - `muted`, `text-start`, `text-end`, `box`;
    - `h2` is the section title.
- **Components** (`<x-print::...>`):
    - `parties` holds the boxes for the customer/vendor/employee;
    - `totals` holds the summary rows, with `emphasis` on the grand total;
    - `amount-in-words` writes the amount in words with `AmountInWords`, for any currency: "فقط … لا غير";
    - `notes` prints nothing when the text is empty.
- **mpdf supports a subset of CSS:**
    - lay out with tables, not flex or floats;
    - margins on tables are ignored.
- **`FinancialDocument` helpers:**
    - `money($amount, $currency|$decimals)` works on the decimal string, so no float rounding;
    - `quantity()`, `percent()`, `date()`, `documentStatus()`.
- **Letterhead details:** a module adds its own by implementing `LetterheadSource` and calling `app(LetterheadFactory::class)->register(X::class)` in its provider. For example, Accounting adds the address and the tax number.
- **Print settings** (`PrintSettings`, page `admin.print-settings.edit`, permissions `Setting View`/`Setting Update`):
    - paper size (A4/A5/Letter);
    - whether to show the logo;
    - whether to show who printed the document;
    - the footer note.
    - `preview` prints a `SampleDocument`.
- **Tests:** `tests/Feature/Modules/Foundation/PrintingTest.php`. For a document, assert `->assertOk()->assertHeader('Content-Type', 'application/pdf')` and the forbidden case.

## 6. Imports

```php
class CategoriesImporter extends Importer
{
    public function columns(): array { return ['name', 'slug']; }   // first row of the file = template
    public function rules(): array { return ['name' => ['required', 'max:255'], 'slug' => ['required', 'unique:categories,slug']]; }
    protected function importRow(array $row): void { Category::create($row); }
}

$result = (new CategoriesImporter)->import($request->spreadsheet());   // ImportRequest validates the file
Inertia::flash(['toast' => [...], 'importResult' => $result->toArray()]);
```

- **Normalising cells:** every cell is turned into trimmed text before validation (`prepareForValidation`), so numeric cells pass `string` rules, and empty cells become null.
- **What is saved:** valid rows are saved in one transaction. Invalid rows are skipped and reported as `{row, column, errors}`.
- **Template:** `template($filename)` downloads an empty file with the columns.
- **Vue:** `<ImportDialog :title :import-route="importMethod" :template-route="importTemplate" :columns />` shows the failures from the `importResult` flash.
- **Reference:** `UsersImporter` reuses Core's `CreateUser` action and refuses to grant Super Admin unless the importer is one.

## 7. Backups

- `admin.backups.*` lists the archives of spatie/laravel-backup on `backup.backup.destination.disks`.
- `CreateBackup` is a unique queued job (database only, or database and files), so a queue worker must run.
- Download and delete use an HMAC id, never a path from the request.
- Permissions: `Backup View List|Create|Download|Delete`.
- The result reaches `Backup View List` holders as a notification. Scheduled backups still run from `routes/console.php`.

## 8. JSON API (v1)

- **Routes:** `routes/api.php` of the module, prefix `api/v1`, names `api.v1.*`, middleware `api`, `SetApiLocale` (from `Accept-Language`) and `throttle:api` (`API_RATE_LIMIT` per minute).
- **Sign-in:**
    - `POST /api/v1/auth/token` with `email`, `password`, `device_name`, plus `code` or `recovery_code` when 2FA is on. It returns a Sanctum token that expires after `API_TOKEN_EXPIRATION_DAYS`.
    - Throttled 5/min per email and IP.
    - Sign-ins appear in the login history.
- **Endpoints:** `DELETE auth/token`, `GET me` (with roles and permissions), and the notifications endpoints.
- **Adding an endpoint:**
    1. Put a controller in `Http/Controllers/Api/V1` (or the module's own `Api/V1`).
    2. Return API Resources.
    3. Document it with `OpenApi\Attributes` (`#[OA\Get(...)]`; see `CurrentUserController`).
    4. Protect it with `auth:sanctum` plus policies or permissions.
    5. Make breaking changes in a new version prefix.
- **Docs:** `php artisan l5-swagger:generate`, served at `/api/documentation`. The root spec is `Http/Controllers/Api/V1/OpenApi.php`; the generated JSON is ignored by git.

## 9. Building blocks

| Class                                               | Use                                                                                            |
| --------------------------------------------------- | ---------------------------------------------------------------------------------------------- |
| `Support/Filters/SearchFilter::on('name', 'email')` | the `filter[search]` of an index QueryBuilder                                                  |
| `Concerns/EnumOptions`                              | `options()` / `values()` for enums with `label()` (e.g. `AuthenticationEvent`, `ExportFormat`) |
| `Support/Recipients::withPermission()`              | notification recipients                                                                        |
| `Support/UserAgent::parse()`                        | "Chrome on Windows" for logs and sessions                                                      |
| `Support/UserSessions`, `Support/Backups`           | session and backup stores                                                                      |

## 10. Calendar

`/admin/calendar` (permission `Calendar View`) shows every module's dates on one month grid, with the overdue items and the next 14 days, plus the reminders staff add themselves (`Reminder`, private unless shared; only the author changes it).

- A module adds a kind of date with a class extending `Support/Calendar/CalendarSource`: `key()`, `label()`, `color()` (brand, info, success, warning, error, orange, purple, pink, gray), `permission()`, `entries($from, $to, $user)` returning `CalendarEntry` objects, and optionally `overdue($today, $user)` for what is late and still open.
- Register it in the module provider's `boot()`: `$this->app->make(Calendar::class)->register(ChequeDueDates::class);`. Sources live in `app/Modules/<Module>/Calendar/`.
- Existing sources: cheque due dates and handovers (Banking), invoices to collect and bills to pay (Invoicing), salaries and employees joining/leaving (Payroll), monthly depreciation (Assets), production orders (Production), fiscal years (Accounting), reminders (Foundation).
- An entry's `color` overrides its source color (red when overdue, grey when settled); `data` carries extra fields for the page (reminders use it to be edited).

## Tests

They live in `tests/Feature/Modules/Foundation/*`, one file per area: Observability, LoginHistory, Sessions, Notifications, DataTransfer, Backups, Api, ModuleGenerator. Follow **motasem-testing**.
