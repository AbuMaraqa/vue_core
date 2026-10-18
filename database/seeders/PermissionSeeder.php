<?php

namespace Database\Seeders;

use App\Modules\Core\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The single place where the permissions of the system are defined.
 *
 * To add a permission, add it to the list below and run:
 * php artisan db:seed --class=PermissionSeeder
 *
 * Permissions removed from the list are deleted, while the roles keep every
 * permission that still exists.
 */
class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            // Dashboard
            [
                'name' => 'Dashboard View',
                'guard_name' => 'web',
                'module_name' => 'Dashboard Management',
            ],

            // User
            [
                'name' => 'User View List',
                'guard_name' => 'web',
                'module_name' => 'User Management',
            ],
            [
                'name' => 'User Create',
                'guard_name' => 'web',
                'module_name' => 'User Management',
            ],
            [
                'name' => 'User Update',
                'guard_name' => 'web',
                'module_name' => 'User Management',
            ],
            [
                'name' => 'User Delete',
                'guard_name' => 'web',
                'module_name' => 'User Management',
            ],
            [
                'name' => 'User Export',
                'guard_name' => 'web',
                'module_name' => 'User Management',
            ],
            [
                'name' => 'User Import',
                'guard_name' => 'web',
                'module_name' => 'User Management',
            ],

            // Roles And Permissions
            [
                'name' => 'Roles And Permissions View List',
                'guard_name' => 'web',
                'module_name' => 'Roles And Permissions Management',
            ],
            [
                'name' => 'Roles And Permissions Create',
                'guard_name' => 'web',
                'module_name' => 'Roles And Permissions Management',
            ],
            [
                'name' => 'Roles And Permissions Update',
                'guard_name' => 'web',
                'module_name' => 'Roles And Permissions Management',
            ],
            [
                'name' => 'Roles And Permissions Delete',
                'guard_name' => 'web',
                'module_name' => 'Roles And Permissions Management',
            ],

            // Media
            [
                'name' => 'Media View List',
                'guard_name' => 'web',
                'module_name' => 'Media Management',
            ],
            [
                'name' => 'Media Create',
                'guard_name' => 'web',
                'module_name' => 'Media Management',
            ],
            [
                'name' => 'Media Delete',
                'guard_name' => 'web',
                'module_name' => 'Media Management',
            ],

            // Setting
            [
                'name' => 'Setting View',
                'guard_name' => 'web',
                'module_name' => 'Settings Management',
            ],
            [
                'name' => 'Setting Update',
                'guard_name' => 'web',
                'module_name' => 'Settings Management',
            ],

            // Activity Log
            [
                'name' => 'Activity Log View List',
                'guard_name' => 'web',
                'module_name' => 'Activity Log Management',
            ],

            // Login History
            [
                'name' => 'Login History View List',
                'guard_name' => 'web',
                'module_name' => 'Login History Management',
            ],
            [
                'name' => 'Login History Export',
                'guard_name' => 'web',
                'module_name' => 'Login History Management',
            ],

            // Backup
            [
                'name' => 'Backup View List',
                'guard_name' => 'web',
                'module_name' => 'Backup Management',
            ],
            [
                'name' => 'Backup Create',
                'guard_name' => 'web',
                'module_name' => 'Backup Management',
            ],
            [
                'name' => 'Backup Download',
                'guard_name' => 'web',
                'module_name' => 'Backup Management',
            ],
            [
                'name' => 'Backup Delete',
                'guard_name' => 'web',
                'module_name' => 'Backup Management',
            ],

            // Calendar
            [
                'name' => 'Calendar View',
                'guard_name' => 'web',
                'module_name' => 'Calendar Management',
            ],

            // Chart Of Accounts
            [
                'name' => 'Account View List',
                'guard_name' => 'web',
                'module_name' => 'Account Management',
            ],
            [
                'name' => 'Account Create',
                'guard_name' => 'web',
                'module_name' => 'Account Management',
            ],
            [
                'name' => 'Account Update',
                'guard_name' => 'web',
                'module_name' => 'Account Management',
            ],
            [
                'name' => 'Account Delete',
                'guard_name' => 'web',
                'module_name' => 'Account Management',
            ],

            // Journal Entries
            [
                'name' => 'Journal Entry View List',
                'guard_name' => 'web',
                'module_name' => 'Journal Entry Management',
            ],
            [
                'name' => 'Journal Entry Create',
                'guard_name' => 'web',
                'module_name' => 'Journal Entry Management',
            ],
            [
                'name' => 'Journal Entry Update',
                'guard_name' => 'web',
                'module_name' => 'Journal Entry Management',
            ],
            [
                'name' => 'Journal Entry Delete',
                'guard_name' => 'web',
                'module_name' => 'Journal Entry Management',
            ],
            [
                'name' => 'Journal Entry Post',
                'guard_name' => 'web',
                'module_name' => 'Journal Entry Management',
            ],
            [
                'name' => 'Journal Entry Reverse',
                'guard_name' => 'web',
                'module_name' => 'Journal Entry Management',
            ],

            // Currencies
            [
                'name' => 'Currency View List',
                'guard_name' => 'web',
                'module_name' => 'Currency Management',
            ],
            [
                'name' => 'Currency Create',
                'guard_name' => 'web',
                'module_name' => 'Currency Management',
            ],
            [
                'name' => 'Currency Update',
                'guard_name' => 'web',
                'module_name' => 'Currency Management',
            ],
            [
                'name' => 'Currency Delete',
                'guard_name' => 'web',
                'module_name' => 'Currency Management',
            ],

            // Taxes
            [
                'name' => 'Tax View List',
                'guard_name' => 'web',
                'module_name' => 'Tax Management',
            ],
            [
                'name' => 'Tax Create',
                'guard_name' => 'web',
                'module_name' => 'Tax Management',
            ],
            [
                'name' => 'Tax Update',
                'guard_name' => 'web',
                'module_name' => 'Tax Management',
            ],
            [
                'name' => 'Tax Delete',
                'guard_name' => 'web',
                'module_name' => 'Tax Management',
            ],

            // Cost Centers
            [
                'name' => 'Cost Center View List',
                'guard_name' => 'web',
                'module_name' => 'Cost Center Management',
            ],
            [
                'name' => 'Cost Center Create',
                'guard_name' => 'web',
                'module_name' => 'Cost Center Management',
            ],
            [
                'name' => 'Cost Center Update',
                'guard_name' => 'web',
                'module_name' => 'Cost Center Management',
            ],
            [
                'name' => 'Cost Center Delete',
                'guard_name' => 'web',
                'module_name' => 'Cost Center Management',
            ],

            // Fiscal Years
            [
                'name' => 'Fiscal Year View List',
                'guard_name' => 'web',
                'module_name' => 'Fiscal Year Management',
            ],
            [
                'name' => 'Fiscal Year Create',
                'guard_name' => 'web',
                'module_name' => 'Fiscal Year Management',
            ],
            [
                'name' => 'Fiscal Year Close',
                'guard_name' => 'web',
                'module_name' => 'Fiscal Year Management',
            ],

            // Budgets
            [
                'name' => 'Budget View List',
                'guard_name' => 'web',
                'module_name' => 'Budget Management',
            ],
            [
                'name' => 'Budget Create',
                'guard_name' => 'web',
                'module_name' => 'Budget Management',
            ],
            [
                'name' => 'Budget Update',
                'guard_name' => 'web',
                'module_name' => 'Budget Management',
            ],
            [
                'name' => 'Budget Delete',
                'guard_name' => 'web',
                'module_name' => 'Budget Management',
            ],

            // Accounting Settings
            [
                'name' => 'Accounting Setting View',
                'guard_name' => 'web',
                'module_name' => 'Accounting Settings Management',
            ],
            [
                'name' => 'Accounting Setting Update',
                'guard_name' => 'web',
                'module_name' => 'Accounting Settings Management',
            ],

            // Financial Reports
            [
                'name' => 'Financial Report View',
                'guard_name' => 'web',
                'module_name' => 'Financial Report Management',
            ],
            [
                'name' => 'Financial Report Export',
                'guard_name' => 'web',
                'module_name' => 'Financial Report Management',
            ],

            // Contacts
            [
                'name' => 'Contact View List',
                'guard_name' => 'web',
                'module_name' => 'Contact Management',
            ],
            [
                'name' => 'Contact View',
                'guard_name' => 'web',
                'module_name' => 'Contact Management',
            ],
            [
                'name' => 'Contact Create',
                'guard_name' => 'web',
                'module_name' => 'Contact Management',
            ],
            [
                'name' => 'Contact Update',
                'guard_name' => 'web',
                'module_name' => 'Contact Management',
            ],
            [
                'name' => 'Contact Delete',
                'guard_name' => 'web',
                'module_name' => 'Contact Management',
            ],
            [
                'name' => 'Contact Export',
                'guard_name' => 'web',
                'module_name' => 'Contact Management',
            ],
            [
                'name' => 'Contact Import',
                'guard_name' => 'web',
                'module_name' => 'Contact Management',
            ],

            // Items
            [
                'name' => 'Item View List',
                'guard_name' => 'web',
                'module_name' => 'Item Management',
            ],
            [
                'name' => 'Item View',
                'guard_name' => 'web',
                'module_name' => 'Item Management',
            ],
            [
                'name' => 'Item Create',
                'guard_name' => 'web',
                'module_name' => 'Item Management',
            ],
            [
                'name' => 'Item Update',
                'guard_name' => 'web',
                'module_name' => 'Item Management',
            ],
            [
                'name' => 'Item Delete',
                'guard_name' => 'web',
                'module_name' => 'Item Management',
            ],
            [
                'name' => 'Item Export',
                'guard_name' => 'web',
                'module_name' => 'Item Management',
            ],
            [
                'name' => 'Item Import',
                'guard_name' => 'web',
                'module_name' => 'Item Management',
            ],

            // Item Categories
            [
                'name' => 'Item Category View List',
                'guard_name' => 'web',
                'module_name' => 'Item Category Management',
            ],
            [
                'name' => 'Item Category Create',
                'guard_name' => 'web',
                'module_name' => 'Item Category Management',
            ],
            [
                'name' => 'Item Category Update',
                'guard_name' => 'web',
                'module_name' => 'Item Category Management',
            ],
            [
                'name' => 'Item Category Delete',
                'guard_name' => 'web',
                'module_name' => 'Item Category Management',
            ],

            // Units
            [
                'name' => 'Unit View List',
                'guard_name' => 'web',
                'module_name' => 'Unit Management',
            ],
            [
                'name' => 'Unit Create',
                'guard_name' => 'web',
                'module_name' => 'Unit Management',
            ],
            [
                'name' => 'Unit Update',
                'guard_name' => 'web',
                'module_name' => 'Unit Management',
            ],
            [
                'name' => 'Unit Delete',
                'guard_name' => 'web',
                'module_name' => 'Unit Management',
            ],

            // Warehouses
            [
                'name' => 'Warehouse View List',
                'guard_name' => 'web',
                'module_name' => 'Warehouse Management',
            ],
            [
                'name' => 'Warehouse Create',
                'guard_name' => 'web',
                'module_name' => 'Warehouse Management',
            ],
            [
                'name' => 'Warehouse Update',
                'guard_name' => 'web',
                'module_name' => 'Warehouse Management',
            ],
            [
                'name' => 'Warehouse Delete',
                'guard_name' => 'web',
                'module_name' => 'Warehouse Management',
            ],

            // Stock Adjustments
            [
                'name' => 'Stock Adjustment View List',
                'guard_name' => 'web',
                'module_name' => 'Stock Adjustment Management',
            ],
            [
                'name' => 'Stock Adjustment View',
                'guard_name' => 'web',
                'module_name' => 'Stock Adjustment Management',
            ],
            [
                'name' => 'Stock Adjustment Create',
                'guard_name' => 'web',
                'module_name' => 'Stock Adjustment Management',
            ],
            [
                'name' => 'Stock Adjustment Cancel',
                'guard_name' => 'web',
                'module_name' => 'Stock Adjustment Management',
            ],

            // Stock Transfers
            [
                'name' => 'Stock Transfer View List',
                'guard_name' => 'web',
                'module_name' => 'Stock Transfer Management',
            ],
            [
                'name' => 'Stock Transfer View',
                'guard_name' => 'web',
                'module_name' => 'Stock Transfer Management',
            ],
            [
                'name' => 'Stock Transfer Create',
                'guard_name' => 'web',
                'module_name' => 'Stock Transfer Management',
            ],
            [
                'name' => 'Stock Transfer Cancel',
                'guard_name' => 'web',
                'module_name' => 'Stock Transfer Management',
            ],

            // Inventory Reports
            [
                'name' => 'Inventory Report View',
                'guard_name' => 'web',
                'module_name' => 'Inventory Report Management',
            ],
            [
                'name' => 'Inventory Report Export',
                'guard_name' => 'web',
                'module_name' => 'Inventory Report Management',
            ],

            // Bank Accounts
            [
                'name' => 'Bank Account View List',
                'guard_name' => 'web',
                'module_name' => 'Bank Account Management',
            ],
            [
                'name' => 'Bank Account View',
                'guard_name' => 'web',
                'module_name' => 'Bank Account Management',
            ],
            [
                'name' => 'Bank Account Create',
                'guard_name' => 'web',
                'module_name' => 'Bank Account Management',
            ],
            [
                'name' => 'Bank Account Update',
                'guard_name' => 'web',
                'module_name' => 'Bank Account Management',
            ],
            [
                'name' => 'Bank Account Delete',
                'guard_name' => 'web',
                'module_name' => 'Bank Account Management',
            ],
            [
                'name' => 'Bank Account Reconcile',
                'guard_name' => 'web',
                'module_name' => 'Bank Account Management',
            ],

            // Bank Transfers
            [
                'name' => 'Bank Transfer View List',
                'guard_name' => 'web',
                'module_name' => 'Bank Transfer Management',
            ],
            [
                'name' => 'Bank Transfer View',
                'guard_name' => 'web',
                'module_name' => 'Bank Transfer Management',
            ],
            [
                'name' => 'Bank Transfer Create',
                'guard_name' => 'web',
                'module_name' => 'Bank Transfer Management',
            ],
            [
                'name' => 'Bank Transfer Cancel',
                'guard_name' => 'web',
                'module_name' => 'Bank Transfer Management',
            ],

            // Bank Vouchers
            [
                'name' => 'Bank Voucher View List',
                'guard_name' => 'web',
                'module_name' => 'Bank Voucher Management',
            ],
            [
                'name' => 'Bank Voucher View',
                'guard_name' => 'web',
                'module_name' => 'Bank Voucher Management',
            ],
            [
                'name' => 'Bank Voucher Create',
                'guard_name' => 'web',
                'module_name' => 'Bank Voucher Management',
            ],
            [
                'name' => 'Bank Voucher Cancel',
                'guard_name' => 'web',
                'module_name' => 'Bank Voucher Management',
            ],

            // Cheques
            [
                'name' => 'Cheque View List',
                'guard_name' => 'web',
                'module_name' => 'Cheque Management',
            ],
            [
                'name' => 'Cheque View',
                'guard_name' => 'web',
                'module_name' => 'Cheque Management',
            ],
            [
                'name' => 'Cheque Create',
                'guard_name' => 'web',
                'module_name' => 'Cheque Management',
            ],
            [
                'name' => 'Cheque Manage',
                'guard_name' => 'web',
                'module_name' => 'Cheque Management',
            ],
            [
                'name' => 'Cheque Export',
                'guard_name' => 'web',
                'module_name' => 'Cheque Management',
            ],

            // Sales Quotations
            [
                'name' => 'Sales Quotation View List',
                'guard_name' => 'web',
                'module_name' => 'Sales Quotation Management',
            ],
            [
                'name' => 'Sales Quotation View',
                'guard_name' => 'web',
                'module_name' => 'Sales Quotation Management',
            ],
            [
                'name' => 'Sales Quotation Create',
                'guard_name' => 'web',
                'module_name' => 'Sales Quotation Management',
            ],
            [
                'name' => 'Sales Quotation Update',
                'guard_name' => 'web',
                'module_name' => 'Sales Quotation Management',
            ],
            [
                'name' => 'Sales Quotation Delete',
                'guard_name' => 'web',
                'module_name' => 'Sales Quotation Management',
            ],
            [
                'name' => 'Sales Quotation Convert',
                'guard_name' => 'web',
                'module_name' => 'Sales Quotation Management',
            ],

            // Sales Invoices and Credit Notes
            [
                'name' => 'Sales Invoice View List',
                'guard_name' => 'web',
                'module_name' => 'Sales Invoice Management',
            ],
            [
                'name' => 'Sales Invoice View',
                'guard_name' => 'web',
                'module_name' => 'Sales Invoice Management',
            ],
            [
                'name' => 'Sales Invoice Create',
                'guard_name' => 'web',
                'module_name' => 'Sales Invoice Management',
            ],
            [
                'name' => 'Sales Invoice Update',
                'guard_name' => 'web',
                'module_name' => 'Sales Invoice Management',
            ],
            [
                'name' => 'Sales Invoice Delete',
                'guard_name' => 'web',
                'module_name' => 'Sales Invoice Management',
            ],
            [
                'name' => 'Sales Invoice Post',
                'guard_name' => 'web',
                'module_name' => 'Sales Invoice Management',
            ],
            [
                'name' => 'Sales Invoice Cancel',
                'guard_name' => 'web',
                'module_name' => 'Sales Invoice Management',
            ],

            // Purchase Bills and Debit Notes
            [
                'name' => 'Purchase Bill View List',
                'guard_name' => 'web',
                'module_name' => 'Purchase Bill Management',
            ],
            [
                'name' => 'Purchase Bill View',
                'guard_name' => 'web',
                'module_name' => 'Purchase Bill Management',
            ],
            [
                'name' => 'Purchase Bill Create',
                'guard_name' => 'web',
                'module_name' => 'Purchase Bill Management',
            ],
            [
                'name' => 'Purchase Bill Update',
                'guard_name' => 'web',
                'module_name' => 'Purchase Bill Management',
            ],
            [
                'name' => 'Purchase Bill Delete',
                'guard_name' => 'web',
                'module_name' => 'Purchase Bill Management',
            ],
            [
                'name' => 'Purchase Bill Post',
                'guard_name' => 'web',
                'module_name' => 'Purchase Bill Management',
            ],
            [
                'name' => 'Purchase Bill Cancel',
                'guard_name' => 'web',
                'module_name' => 'Purchase Bill Management',
            ],

            // Customer Receipts
            [
                'name' => 'Customer Receipt View List',
                'guard_name' => 'web',
                'module_name' => 'Customer Receipt Management',
            ],
            [
                'name' => 'Customer Receipt View',
                'guard_name' => 'web',
                'module_name' => 'Customer Receipt Management',
            ],
            [
                'name' => 'Customer Receipt Create',
                'guard_name' => 'web',
                'module_name' => 'Customer Receipt Management',
            ],
            [
                'name' => 'Customer Receipt Cancel',
                'guard_name' => 'web',
                'module_name' => 'Customer Receipt Management',
            ],

            // Vendor Payments
            [
                'name' => 'Vendor Payment View List',
                'guard_name' => 'web',
                'module_name' => 'Vendor Payment Management',
            ],
            [
                'name' => 'Vendor Payment View',
                'guard_name' => 'web',
                'module_name' => 'Vendor Payment Management',
            ],
            [
                'name' => 'Vendor Payment Create',
                'guard_name' => 'web',
                'module_name' => 'Vendor Payment Management',
            ],
            [
                'name' => 'Vendor Payment Cancel',
                'guard_name' => 'web',
                'module_name' => 'Vendor Payment Management',
            ],

            // Invoicing Reports
            [
                'name' => 'Invoicing Report View',
                'guard_name' => 'web',
                'module_name' => 'Invoicing Report Management',
            ],
            [
                'name' => 'Invoicing Report Export',
                'guard_name' => 'web',
                'module_name' => 'Invoicing Report Management',
            ],

            // Fixed Assets
            [
                'name' => 'Asset View List',
                'guard_name' => 'web',
                'module_name' => 'Asset Management',
            ],
            [
                'name' => 'Asset View',
                'guard_name' => 'web',
                'module_name' => 'Asset Management',
            ],
            [
                'name' => 'Asset Create',
                'guard_name' => 'web',
                'module_name' => 'Asset Management',
            ],
            [
                'name' => 'Asset Update',
                'guard_name' => 'web',
                'module_name' => 'Asset Management',
            ],
            [
                'name' => 'Asset Dispose',
                'guard_name' => 'web',
                'module_name' => 'Asset Management',
            ],
            [
                'name' => 'Asset Cancel',
                'guard_name' => 'web',
                'module_name' => 'Asset Management',
            ],
            [
                'name' => 'Asset Export',
                'guard_name' => 'web',
                'module_name' => 'Asset Management',
            ],

            // Asset Categories
            [
                'name' => 'Asset Category View List',
                'guard_name' => 'web',
                'module_name' => 'Asset Category Management',
            ],
            [
                'name' => 'Asset Category Create',
                'guard_name' => 'web',
                'module_name' => 'Asset Category Management',
            ],
            [
                'name' => 'Asset Category Update',
                'guard_name' => 'web',
                'module_name' => 'Asset Category Management',
            ],
            [
                'name' => 'Asset Category Delete',
                'guard_name' => 'web',
                'module_name' => 'Asset Category Management',
            ],

            // Depreciation Runs
            [
                'name' => 'Depreciation Run View List',
                'guard_name' => 'web',
                'module_name' => 'Depreciation Run Management',
            ],
            [
                'name' => 'Depreciation Run Create',
                'guard_name' => 'web',
                'module_name' => 'Depreciation Run Management',
            ],
            [
                'name' => 'Depreciation Run Cancel',
                'guard_name' => 'web',
                'module_name' => 'Depreciation Run Management',
            ],

            // Employees
            [
                'name' => 'Employee View List',
                'guard_name' => 'web',
                'module_name' => 'Employee Management',
            ],
            [
                'name' => 'Employee View',
                'guard_name' => 'web',
                'module_name' => 'Employee Management',
            ],
            [
                'name' => 'Employee Create',
                'guard_name' => 'web',
                'module_name' => 'Employee Management',
            ],
            [
                'name' => 'Employee Update',
                'guard_name' => 'web',
                'module_name' => 'Employee Management',
            ],
            [
                'name' => 'Employee Delete',
                'guard_name' => 'web',
                'module_name' => 'Employee Management',
            ],

            // Pay Components
            [
                'name' => 'Pay Component View List',
                'guard_name' => 'web',
                'module_name' => 'Pay Component Management',
            ],
            [
                'name' => 'Pay Component Create',
                'guard_name' => 'web',
                'module_name' => 'Pay Component Management',
            ],
            [
                'name' => 'Pay Component Update',
                'guard_name' => 'web',
                'module_name' => 'Pay Component Management',
            ],
            [
                'name' => 'Pay Component Delete',
                'guard_name' => 'web',
                'module_name' => 'Pay Component Management',
            ],

            // Payroll Runs
            [
                'name' => 'Payroll Run View List',
                'guard_name' => 'web',
                'module_name' => 'Payroll Run Management',
            ],
            [
                'name' => 'Payroll Run View',
                'guard_name' => 'web',
                'module_name' => 'Payroll Run Management',
            ],
            [
                'name' => 'Payroll Run Create',
                'guard_name' => 'web',
                'module_name' => 'Payroll Run Management',
            ],
            [
                'name' => 'Payroll Run Update',
                'guard_name' => 'web',
                'module_name' => 'Payroll Run Management',
            ],
            [
                'name' => 'Payroll Run Post',
                'guard_name' => 'web',
                'module_name' => 'Payroll Run Management',
            ],
            [
                'name' => 'Payroll Run Pay',
                'guard_name' => 'web',
                'module_name' => 'Payroll Run Management',
            ],
            [
                'name' => 'Payroll Run Cancel',
                'guard_name' => 'web',
                'module_name' => 'Payroll Run Management',
            ],

            // Bills Of Materials
            [
                'name' => 'Bill Of Materials View List',
                'guard_name' => 'web',
                'module_name' => 'Bill Of Materials Management',
            ],
            [
                'name' => 'Bill Of Materials Create',
                'guard_name' => 'web',
                'module_name' => 'Bill Of Materials Management',
            ],
            [
                'name' => 'Bill Of Materials Update',
                'guard_name' => 'web',
                'module_name' => 'Bill Of Materials Management',
            ],
            [
                'name' => 'Bill Of Materials Delete',
                'guard_name' => 'web',
                'module_name' => 'Bill Of Materials Management',
            ],

            // Production Orders
            [
                'name' => 'Production Order View List',
                'guard_name' => 'web',
                'module_name' => 'Production Order Management',
            ],
            [
                'name' => 'Production Order View',
                'guard_name' => 'web',
                'module_name' => 'Production Order Management',
            ],
            [
                'name' => 'Production Order Create',
                'guard_name' => 'web',
                'module_name' => 'Production Order Management',
            ],
            [
                'name' => 'Production Order Update',
                'guard_name' => 'web',
                'module_name' => 'Production Order Management',
            ],
            [
                'name' => 'Production Order Delete',
                'guard_name' => 'web',
                'module_name' => 'Production Order Management',
            ],
            [
                'name' => 'Production Order Start',
                'guard_name' => 'web',
                'module_name' => 'Production Order Management',
            ],
            [
                'name' => 'Production Order Complete',
                'guard_name' => 'web',
                'module_name' => 'Production Order Management',
            ],
            [
                'name' => 'Production Order Reverse',
                'guard_name' => 'web',
                'module_name' => 'Production Order Management',
            ],

            // Production Reports
            [
                'name' => 'Production Report View',
                'guard_name' => 'web',
                'module_name' => 'Production Report Management',
            ],
            [
                'name' => 'Production Report Export',
                'guard_name' => 'web',
                'module_name' => 'Production Report Management',
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(
                ['name' => $permission['name'], 'guard_name' => $permission['guard_name']],
                ['module_name' => $permission['module_name']],
            );
        }

        $deleted = Permission::query()->whereNotIn('name', array_column($permissions, 'name'))->delete();

        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }

        Role::findByName(UserRole::SuperAdmin->value, 'web')->syncPermissions(array_column($permissions, 'name'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command->info(sprintf('%d permissions synced, %d removed, all assigned to %s.', count($permissions), $deleted, UserRole::SuperAdmin->value));
    }
}
