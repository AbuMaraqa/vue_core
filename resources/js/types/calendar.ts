/**
 * A color name of the calendar (CalendarSource::color() on the server).
 */
export type CalendarColor =
    | 'brand'
    | 'info'
    | 'success'
    | 'warning'
    | 'error'
    | 'orange'
    | 'purple'
    | 'pink'
    | 'gray';

/**
 * A kind of date a module puts on the calendar, e.g. cheque due dates.
 */
export type CalendarSourceInfo = {
    key: string;
    label: string;
    color: CalendarColor;
};

/**
 * One dated thing of the calendar (CalendarEntry on the server).
 */
export type CalendarEntry = {
    id: string;
    type: string;
    date: string;
    time: string | null;
    title: string;
    subtitle: string | null;
    url: string | null;
    status: string | null;
    amount: string | null;
    currency: string | null;
    decimals: number;
    color: CalendarColor | null;
    overdue: boolean;
    data: {
        reminder_id?: number;
        notes?: string | null;
        is_shared?: boolean;
        editable?: boolean;
    };
};
