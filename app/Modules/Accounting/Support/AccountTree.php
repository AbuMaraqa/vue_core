<?php

namespace App\Modules\Accounting\Support;

use App\Modules\Accounting\Enums\AccountSubtype;
use App\Modules\Accounting\Models\Account;
use Illuminate\Database\Eloquent\Collection;

/**
 * The chart of accounts as a list in tree order (each account followed by
 * its children), with the depth of each account, ready for a tree table.
 */
class AccountTree
{
    /**
     * @param  Collection<int, Account>  $accounts
     * @return list<array{account: Account, depth: int, has_children: bool}>
     */
    public function flatten(Collection $accounts): array
    {
        $children = $accounts->sortBy('code', SORT_NATURAL)->groupBy(fn (Account $account): int => $account->parent_id ?? 0);
        $rows = [];

        $walk = function (int $parentId, int $depth) use (&$walk, &$rows, $children): void {
            foreach ($children->get($parentId, []) as $account) {
                $rows[] = [
                    'account' => $account,
                    'depth' => $depth,
                    'has_children' => $children->has($account->id),
                ];

                $walk($account->id, $depth + 1);
            }
        };

        $walk(0, 0);

        return $rows;
    }

    /**
     * Options for a select of accounts that can receive postings, optionally
     * only those of the given subtypes.
     *
     * @return list<array{value: int, label: string, hint: string}>
     */
    public function postableOptions(AccountSubtype ...$subtypes): array
    {
        return array_values(Account::query()
            ->postable()
            ->when($subtypes !== [], fn ($query) => $query->whereIn('subtype', $subtypes))
            ->orderBy('code')
            ->get()
            ->map(fn (Account $account): array => [
                'value' => $account->id,
                'label' => $account->label(),
                'hint' => $account->subtype->label(),
            ])
            ->all());
    }

    /**
     * Options for a select of group accounts, e.g. the parent of a new account.
     *
     * @return list<array{value: int, label: string, hint: string}>
     */
    public function groupOptions(): array
    {
        return array_values(Account::query()
            ->where('is_group', true)
            ->orderBy('code')
            ->get()
            ->map(fn (Account $account): array => [
                'value' => $account->id,
                'label' => $account->label(),
                'hint' => $account->type->label(),
            ])
            ->all());
    }
}
