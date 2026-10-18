import type { PaginationLink, ResourceAbilities } from './admin';
import type { ComboboxOption } from './ui';

/**
 * How amounts of the base currency are shown.
 */
export type PayrollCurrency = {
    code: string;
    decimal_places: number;
};

export type PayComponentType =
    | 'earning'
    | 'deduction'
    | 'employer_contribution';

export type PayCalculation = 'fixed' | 'percent_of_basic';

export type PayrollRunStatus = 'draft' | 'posted' | 'paid' | 'cancelled';

export type PayrollStatusColor = 'light' | 'info' | 'success' | 'error';

export type EmployeePaymentMethod = 'bank' | 'cash';

/**
 * Laravel's paginator serialized as is (not a Resource collection).
 */
export type PayrollPage<T> = {
    data: T[];
    current_page: number;
    from: number | null;
    last_page: number;
    links: PaginationLink[];
    path: string;
    per_page: number;
    to: number | null;
    total: number;
    first_page_url: string | null;
    last_page_url: string | null;
    next_page_url: string | null;
    prev_page_url: string | null;
};

/**
 * An employee (EmployeeController::row).
 */
export type Employee = {
    id: number;
    code: string;
    name: string;
    national_id: string | null;
    position: string | null;
    cost_center_id: number | null;
    cost_center: string | null;
    hire_date: string;
    termination_date: string | null;
    basic_salary: string;
    payment_method: EmployeePaymentMethod;
    bank_name: string | null;
    iban: string | null;
    email: string | null;
    phone: string | null;
    notes: string | null;
    is_active: boolean;
    can: ResourceAbilities;
};

/**
 * A component assigned to an employee, as the edit form reads it.
 */
export type EmployeeComponentInput = {
    pay_component_id: number;
    value: string;
};

export type EmployeeForEdit = Employee & {
    components: EmployeeComponentInput[];
};

/**
 * A component assigned to an employee, as the details page shows it.
 */
export type EmployeeComponent = {
    name: string;
    type: PayComponentType;
    type_label: string;
    calculation: PayCalculation;
    value: string;
};

export type EmployeeDetails = Employee & {
    components: EmployeeComponent[];
};

/**
 * A payslip in the employee's history.
 */
export type EmployeePayslip = {
    id: number;
    run_id: number;
    period: string;
    status_label: string;
    status_color: PayrollStatusColor;
    basic: string;
    earnings: string;
    deductions: string;
    net_pay: string;
};

/**
 * An active pay component offered by the employee form.
 */
export type PayComponentOption = ComboboxOption & {
    value: number;
    type: PayComponentType;
    type_label: string;
    calculation: PayCalculation;
    default_value: string;
};

/**
 * An active pay component offered when adjusting a payslip.
 */
export type PayslipComponentOption = ComboboxOption & {
    value: number;
    type: PayComponentType;
};

/**
 * A row of the pay components page (PayComponentController::index).
 */
export type PayComponentRow = {
    id: number;
    name: string;
    name_translations: Record<string, string>;
    type: PayComponentType;
    type_label: string;
    calculation: PayCalculation;
    calculation_label: string;
    default_value: string;
    account_id: number;
    account: string;
    liability_account_id: number | null;
    liability_account: string | null;
    is_active: boolean;
};

/**
 * A payroll run's summary (PayrollRunController::summary).
 */
export type PayrollRunSummary = {
    id: number;
    number: string | null;
    period: string;
    period_start: string;
    period_end: string;
    payment_date: string | null;
    status: PayrollRunStatus;
    status_label: string;
    status_color: PayrollStatusColor;
    employees: number;
    gross_total: string;
    deductions_total: string;
    employer_total: string;
    net_total: string;
};

export type PayslipLine = {
    pay_component_id: number;
    name: string;
    type: PayComponentType;
    amount: string;
};

export type Payslip = {
    id: number;
    employee_id: number;
    employee: string;
    cost_center: string | null;
    days_worked: number;
    days_in_period: number;
    basic: string;
    earnings_total: string;
    deductions_total: string;
    employer_total: string;
    net_pay: string;
    lines: PayslipLine[];
};

export type PayrollRun = PayrollRunSummary & {
    bank_account: string | null;
    created_by: string | null;
    journal_entries: Array<{
        id: number;
        number: string | null;
        description: string | null;
    }>;
    payslips: Payslip[];
    can: {
        update: boolean;
        post: boolean;
        pay: boolean;
        /**
         * Simple mode's single step: post (if still a draft) and pay.
         */
        post_and_pay: boolean;
        reverse: boolean;
    };
};
