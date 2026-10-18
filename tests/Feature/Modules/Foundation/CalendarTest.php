<?php

use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Banking\Enums\ChequeDirection;
use App\Modules\Banking\Enums\ChequeStatus;
use App\Modules\Banking\Models\Cheque;
use App\Modules\Foundation\Models\Reminder;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Production\Models\ProductionOrder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-06-15 10:00:00');
});

/**
 * The ids of the entries of a calendar prop.
 *
 * @return list<string>
 */
function calendarIds(Assert $page, string $prop): array
{
    return array_column($page->toArray()['props'][$prop], 'id');
}

describe('page', function () {
    test('the calendar needs its permission', function () {
        $this->actingAs(staffMember())
            ->get(route('admin.calendar.index'))
            ->assertForbidden();
    });

    test('the month grid runs from the Saturday before the 1st to the Friday after the last day', function () {
        $this->actingAs(staffMember('Calendar View'))
            ->get(route('admin.calendar.index', ['month' => '2026-06']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/calendar/Index')
                ->where('month', '2026-06')
                ->where('range.from', '2026-05-30')
                ->where('range.to', '2026-07-03')
                ->where('today', '2026-06-15'));
    });

    test('a month that is not valid falls back to the current one', function () {
        $this->actingAs(staffMember('Calendar View'))
            ->get(route('admin.calendar.index', ['month' => 'nonsense']))
            ->assertInertia(fn (Assert $page) => $page->where('month', '2026-06'));
    });
});

describe('cheques', function () {
    test('a cheque shows on the day it was received and on its due date', function () {
        $cheque = Cheque::factory()->create(['issue_date' => '2026-06-02', 'due_date' => '2026-06-20']);

        $this->actingAs(staffMember('Calendar View', 'Cheque View List'))
            ->get(route('admin.calendar.index', ['month' => '2026-06']))
            ->assertInertia(function (Assert $page) use ($cheque) {
                $entries = collect($page->toArray()['props']['entries'])->keyBy('id');

                expect($entries["cheque-handover-{$cheque->id}"]['date'])->toBe('2026-06-02')
                    ->and($entries["cheque-due-{$cheque->id}"]['date'])->toBe('2026-06-20')
                    ->and($entries["cheque-due-{$cheque->id}"]['amount'])->toBe('100.0000')
                    ->and(calendarIds($page, 'upcoming'))->toContain("cheque-due-{$cheque->id}");
            });
    });

    test('an open cheque past its due date is overdue; a collected one is not', function () {
        $late = Cheque::factory()->create(['issue_date' => '2026-05-01', 'due_date' => '2026-06-10']);
        $collected = Cheque::factory()->create(['issue_date' => '2026-05-01', 'due_date' => '2026-06-11', 'status' => ChequeStatus::Collected]);

        $this->actingAs(staffMember('Calendar View', 'Cheque View List'))
            ->get(route('admin.calendar.index'))
            ->assertInertia(function (Assert $page) use ($late, $collected) {
                $entries = collect($page->toArray()['props']['entries'])->keyBy('id');

                expect(calendarIds($page, 'overdue'))->toBe(["cheque-due-{$late->id}"])
                    ->and($entries["cheque-due-{$late->id}"]['color'])->toBe('error')
                    ->and($entries["cheque-due-{$collected->id}"]['color'])->toBe('gray');
            });
    });

    test('issued cheques read as handed over', function () {
        $cheque = Cheque::factory()->create(['direction' => ChequeDirection::Outgoing, 'status' => ChequeStatus::Issued, 'number' => '777', 'issue_date' => '2026-06-03']);

        $this->actingAs(staffMember('Calendar View', 'Cheque View List'))
            ->get(route('admin.calendar.index'))
            ->assertInertia(fn (Assert $page) => expect(collect($page->toArray()['props']['entries'])->firstWhere('id', "cheque-handover-{$cheque->id}")['title'])
                ->toBe('Cheque 777 handed over'));
    });

    test('cheques are hidden from staff who cannot see them', function () {
        Cheque::factory()->create(['due_date' => '2026-06-20']);

        $this->actingAs(staffMember('Calendar View'))
            ->get(route('admin.calendar.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('entries', [])
                ->where('sources', fn ($sources) => ! collect($sources)->pluck('key')->contains('cheque_due')));
    });
});

describe('invoices and production', function () {
    test('only invoices still owed show on their due date', function () {
        $owed = Invoice::factory()->create(['status' => DocumentStatus::Posted, 'number' => 'INV-1', 'total' => '50', 'paid_amount' => '20', 'due_date' => '2026-06-25']);
        Invoice::factory()->create(['status' => DocumentStatus::Posted, 'number' => 'INV-2', 'total' => '50', 'paid_amount' => '50', 'due_date' => '2026-06-25']);
        Invoice::factory()->create(['due_date' => '2026-06-25']);

        $this->actingAs(staffMember('Calendar View', 'Sales Invoice View List'))
            ->get(route('admin.calendar.index'))
            ->assertInertia(function (Assert $page) use ($owed) {
                $entries = $page->toArray()['props']['entries'];

                expect(array_column($entries, 'id'))->toBe(["invoice-due-{$owed->id}"])
                    ->and($entries[0]['amount'])->toBe('30.0000');
            });
    });

    test('a draft production order whose day has passed is overdue', function () {
        $order = ProductionOrder::factory()->create(['date' => '2026-06-12']);

        $this->actingAs(staffMember('Calendar View', 'Production Order View List'))
            ->get(route('admin.calendar.index'))
            ->assertInertia(fn (Assert $page) => expect(calendarIds($page, 'overdue'))->toBe(["production-{$order->id}"]));
    });
});

describe('reminders', function () {
    test('a reminder is added for its author', function () {
        $user = staffMember('Calendar View');

        $this->actingAs($user)
            ->post(route('admin.calendar.reminders.store'), [
                'title' => 'Deliver the kitchen',
                'date' => '2026-06-18',
                'time' => '09:30',
                'color' => 'success',
            ])
            ->assertSessionHasNoErrors();

        $reminder = Reminder::query()->sole();

        expect($reminder)
            ->user_id->toBe($user->id)
            ->is_shared->toBeFalse();

        $this->get(route('admin.calendar.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('entries.0.id', "reminder-{$reminder->id}")
                ->where('entries.0.time', '09:30')
                ->where('entries.0.color', 'success')
                ->where('entries.0.data.editable', true));
    });

    test('private reminders stay private; shared ones are seen but not changed by others', function () {
        $author = staffMember('Calendar View');
        $private = Reminder::factory()->for($author)->create(['date' => '2026-06-18']);
        $shared = Reminder::factory()->for($author)->shared()->create(['date' => '2026-06-19']);
        $colleague = staffMember('Calendar View');

        $this->actingAs($colleague)
            ->get(route('admin.calendar.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('entries.0.id', "reminder-{$shared->id}")
                ->where('entries.0.data.editable', false)
                ->count('entries', 1));

        $this->put(route('admin.calendar.reminders.update', $shared), ['title' => 'Mine now', 'date' => '2026-06-19', 'color' => 'brand'])
            ->assertForbidden();
        $this->delete(route('admin.calendar.reminders.destroy', $private))->assertForbidden();

        expect(Reminder::query()->count())->toBe(2);
    });

    test('the author changes and deletes a reminder', function () {
        $author = staffMember('Calendar View');
        $reminder = Reminder::factory()->for($author)->create();

        $this->actingAs($author)
            ->put(route('admin.calendar.reminders.update', $reminder), ['title' => 'Renew licence', 'date' => '2026-07-01', 'color' => 'error', 'is_shared' => true])
            ->assertSessionHasNoErrors();

        expect($reminder->refresh())
            ->title->toBe('Renew licence')
            ->is_shared->toBeTrue();

        $this->delete(route('admin.calendar.reminders.destroy', $reminder))->assertSessionHasNoErrors();

        expect(Reminder::query()->count())->toBe(0);
    });

    test('a reminder needs a title, a date and a known color', function () {
        $this->actingAs(staffMember('Calendar View'))
            ->post(route('admin.calendar.reminders.store'), ['title' => '', 'date' => 'x', 'time' => '25:00', 'color' => 'rainbow'])
            ->assertSessionHasErrors(['title', 'date', 'time', 'color']);
    });
});
