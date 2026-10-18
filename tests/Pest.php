<?php

use App\Models\User;
use App\Modules\Accounting\Models\Account;
use Database\Seeders\AccountingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create a user who can open the admin panel and holds the given permissions.
 */
function staffMember(string ...$permissions): User
{
    return User::factory()
        ->withPermissions('Dashboard View', ...$permissions)
        ->create();
}

/**
 * Seed the starting accounting data: currencies, chart of accounts with its
 * default accounts, taxes and the current fiscal year.
 */
function seedAccounting(): void
{
    test()->seed(AccountingSeeder::class);
}

/**
 * An account of the seeded chart of accounts, by code.
 */
function account(string $code): Account
{
    return Account::query()->where('code', $code)->firstOrFail();
}
