<?php

use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Support\AccountBalances;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\AssetCategory;
use App\Modules\Assets\Models\DepreciationRun;
use App\Modules\Assets\Support\DepreciationSchedule;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A computer bought on 1 January for 1,200 and depreciated over 12 months:
 * 100 a month.
 */
beforeEach(function () {
    $this->travelTo('2026-06-15 10:00:00');
    seedAccounting();

    $this->category = AssetCategory::query()->create([
        'name' => ['en' => 'Computers'],
        'asset_account_id' => account('1202')->id,
        'accumulated_depreciation_account_id' => account('120902')->id,
        'depreciation_expense_account_id' => account('5205')->id,
        'method' => 'straight_line',
        'useful_life_months' => 12,
    ]);
});

function ledger(string $code): string
{
    return Decimal::round(app(AccountBalances::class)->asOf(now()->endOfYear())[account($code)->id] ?? '0');
}

/**
 * @param  array<string, mixed>  $data
 */
function registerAsset(array $data = []): Asset
{
    test()->actingAs(staffMember('Asset Create'))
        ->post(route('admin.assets.assets.store'), [
            'name' => 'Laptop',
            'category_id' => test()->category->id,
            'acquisition_date' => '2026-01-01',
            'cost' => '1200',
            'funding_account_id' => account('110101')->id,
            ...$data,
        ])
        ->assertSessionHasNoErrors();

    return Asset::query()->latest('id')->firstOrFail();
}

function runDepreciation(string $month): void
{
    test()->actingAs(staffMember('Depreciation Run Create'))
        ->post(route('admin.assets.depreciation.store'), ['month' => $month])
        ->assertSessionHasNoErrors();
}

test('registering an asset posts its acquisition', function () {
    $asset = registerAsset();

    expect($asset)
        ->number->toStartWith('FA-2026-')
        ->useful_life_months->toBe(12)
        ->method->value->toBe('straight_line')
        ->and(ledger('1202'))->toBe('1200.0000')
        ->and(ledger('110101'))->toBe('-1200.0000');
});

test('a run catches up the months an asset missed, then posts one month at a time', function () {
    $asset = registerAsset();

    runDepreciation('2026-05');

    expect($asset->fresh())
        ->accumulated_depreciation->toBe('500.0000')
        ->depreciated_until->toDateString()->toBe('2026-05-31')
        ->and(ledger('5205'))->toBe('500.0000')
        ->and(ledger('120902'))->toBe('-500.0000');

    runDepreciation('2026-06');

    expect($asset->fresh()->accumulated_depreciation)->toBe('600.0000');

    $this->actingAs(staffMember('Depreciation Run Create'))
        ->post(route('admin.assets.depreciation.store'), ['month' => '2026-06'])
        ->assertSessionHasErrors('accounting');
});

test('cancelling the latest run puts the assets back', function () {
    $asset = registerAsset();
    runDepreciation('2026-05');
    runDepreciation('2026-06');
    [$may, $june] = DepreciationRun::query()->orderBy('id')->get()->all();

    $this->actingAs(staffMember('Depreciation Run Cancel'))
        ->post(route('admin.assets.depreciation.cancel', $may))
        ->assertSessionHasErrors('accounting');

    $this->actingAs(staffMember('Depreciation Run Cancel'))
        ->post(route('admin.assets.depreciation.cancel', $june))
        ->assertRedirect();

    expect($june->fresh()->status)->toBe(DocumentStatus::Cancelled)
        ->and($asset->fresh())
        ->accumulated_depreciation->toBe('500.0000')
        ->depreciated_until->toDateString()->toBe('2026-05-31')
        ->and(ledger('5205'))->toBe('500.0000');
});

test('an asset is fully depreciated at the end of its life, down to its salvage value', function () {
    $asset = registerAsset(['salvage_value' => '200', 'acquisition_date' => '2025-01-01', 'funding_account_id' => null]);

    $schedule = app(DepreciationSchedule::class)->rows($asset);

    expect($schedule)->toHaveCount(12)
        ->and($schedule[0]['amount'])->toBe('83.3330')
        ->and($schedule[11]['amount'])->toBe('83.3370')
        ->and($schedule[11]['book_value'])->toBe('200.0000');

    runDepreciation('2026-01');

    expect($asset->fresh())->status->toBe(AssetStatus::FullyDepreciated)->accumulated_depreciation->toBe('1000.0000');
});

test('declining balance takes a rate of what is left', function () {
    $asset = registerAsset(['method' => 'declining_balance', 'useful_life_months' => 24, 'cost' => '1000', 'declining_rate' => '50']);

    $schedule = app(DepreciationSchedule::class)->rows($asset);

    expect($schedule[0]['amount'])->toBe('41.6670')
        ->and($schedule[1]['amount'])->toBe('39.9310')
        ->and(end($schedule)['book_value'])->toBe('0.0000');
});

test('an asset carried over keeps the depreciation already taken', function () {
    $asset = registerAsset([
        'cost' => '1000', 'salvage_value' => '100', 'opening_depreciation' => '400', 'useful_life_months' => 6,
        'funding_account_id' => account('3103')->id, 'depreciation_start_date' => '2026-01-01',
    ]);

    $schedule = app(DepreciationSchedule::class)->rows($asset);

    expect($asset->accumulated_depreciation)->toBe('400.0000')
        ->and($schedule[0]['amount'])->toBe('83.3330')
        ->and($schedule[5]['amount'])->toBe('83.3350')
        ->and(ledger('120902'))->toBe('-400.0000')
        ->and(ledger('3103'))->toBe('-600.0000');
});

test('the register counts the depreciation taken up to its date', function () {
    registerAsset();
    runDepreciation('2026-05');
    runDepreciation('2026-06');

    $this->actingAs(staffMember('Asset View List'))
        ->get(route('admin.assets.reports.register', ['as_of' => '2026-05-31']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.rows.0.accumulated', '500.0000')
            ->where('report.rows.0.book_value', '700.0000')
            ->where('report.totals.accumulated', '500.0000'));

    $this->actingAs(staffMember('Asset View List'))
        ->get(route('admin.assets.reports.register', ['as_of' => '2026-06-30']))
        ->assertInertia(fn (Assert $page) => $page->where('report.rows.0.accumulated', '600.0000'));
});

test('the schedule up to a month holds the same rows as the whole schedule', function () {
    $asset = registerAsset(['method' => 'declining_balance', 'useful_life_months' => 24, 'cost' => '1000', 'declining_rate' => '50']);
    $schedule = app(DepreciationSchedule::class);

    expect($schedule->rows($asset, now()->setDate(2026, 3, 31)))->toBe(array_slice($schedule->rows($asset), 0, 3));
});

test('salvage value and earlier depreciation cannot exceed the cost', function () {
    $this->actingAs(staffMember('Asset Create'))
        ->post(route('admin.assets.assets.store'), [
            'name' => 'Laptop', 'category_id' => $this->category->id, 'acquisition_date' => '2026-01-01',
            'cost' => '100', 'salvage_value' => '80', 'opening_depreciation' => '30',
        ])
        ->assertSessionHasErrors('salvage_value');
});

test('disposing catches up depreciation and posts the gain', function () {
    $asset = registerAsset();
    runDepreciation('2026-05');

    $this->actingAs(staffMember('Asset Dispose'))
        ->post(route('admin.assets.assets.dispose', $asset), ['date' => '2026-06-20', 'proceeds' => '800', 'proceeds_account_id' => account('110101')->id])
        ->assertSessionHasNoErrors();

    expect($asset->fresh())->status->toBe(AssetStatus::Disposed)->accumulated_depreciation->toBe('600.0000')
        ->and(ledger('1202'))->toBe('0.0000')
        ->and(ledger('120902'))->toBe('0.0000')
        ->and(ledger('5205'))->toBe('600.0000')
        ->and(ledger('4203'))->toBe('-200.0000')
        ->and(ledger('110101'))->toBe('-400.0000');
});

test('scrapping posts the remaining book value as a loss', function () {
    $asset = registerAsset();

    $this->actingAs(staffMember('Asset Dispose'))
        ->post(route('admin.assets.assets.dispose', $asset), ['date' => '2026-03-10'])
        ->assertSessionHasNoErrors();

    expect(ledger('5205'))->toBe('300.0000')
        ->and(ledger('5302'))->toBe('900.0000')
        ->and(ledger('1202'))->toBe('0.0000');
});

test('an asset with no depreciation can be cancelled', function () {
    $asset = registerAsset();

    $this->actingAs(staffMember('Asset Cancel'))->post(route('admin.assets.assets.cancel', $asset))->assertRedirect();

    expect($asset->fresh()->status)->toBe(AssetStatus::Cancelled)
        ->and(ledger('1202'))->toBe('0.0000');

    $other = registerAsset();
    runDepreciation('2026-02');

    $this->actingAs(staffMember('Asset Cancel'))->post(route('admin.assets.assets.cancel', $other))->assertForbidden();
});

test('the register shows the book value on a date', function () {
    registerAsset();
    runDepreciation('2026-05');

    $this->actingAs(staffMember('Asset View List'))
        ->get(route('admin.assets.reports.register', ['as_of' => '2026-03-31']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/assets/reports/Register')
            ->where('report.totals.cost', '1200.0000')
            ->where('report.totals.accumulated', '0.0000'));

    $this->actingAs(staffMember('Asset View List'))
        ->get(route('admin.assets.reports.register', ['as_of' => '2026-05-31']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.totals.accumulated', '500.0000')
            ->where('report.totals.book_value', '700.0000'));
});

test('the asset card prints as a PDF for those who may view the asset', function () {
    $asset = registerAsset(['serial_number' => 'SN-001', 'notes' => 'Second floor']);
    runDepreciation('2026-02');

    $response = $this->actingAs(staffMember('Asset View'))
        ->get(route('admin.assets.assets.pdf', $asset))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($response->getContent())->toStartWith('%PDF');

    $this->actingAs(staffMember())->get(route('admin.assets.assets.pdf', $asset))->assertForbidden();
});

test('a depreciation run prints as a PDF for those who may view the runs', function () {
    registerAsset();
    runDepreciation('2026-02');
    $run = DepreciationRun::query()->sole();

    $response = $this->actingAs(staffMember('Depreciation Run View List'))
        ->get(route('admin.assets.depreciation.pdf', $run))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($response->getContent())->toStartWith('%PDF');

    $this->actingAs(staffMember())->get(route('admin.assets.depreciation.pdf', $run))->assertForbidden();
});
