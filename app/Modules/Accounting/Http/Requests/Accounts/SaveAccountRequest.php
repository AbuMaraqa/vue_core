<?php

namespace App\Modules\Accounting\Http\Requests\Accounts;

use App\Modules\Accounting\Enums\AccountSubtype;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Foundation\Http\Requests\Concerns\ValidatesTranslations;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create or update an account. The type comes from the subtype, and an
 * account under a parent must be of the parent's type.
 */
class SaveAccountRequest extends FormRequest
{
    use ValidatesTranslations;

    public function authorize(): bool
    {
        $account = $this->account();

        return $account ? $this->user()->can('update', $account) : $this->user()->can('create', Account::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $account = $this->account();

        return [
            'parent_id' => ['nullable', 'integer', Rule::exists(Account::class, 'id')->where('is_group', true), $this->parentIsNotDescendant(...)],
            'code' => ['required', 'string', 'max:20', 'regex:/^[0-9A-Za-z.\-]+$/', Rule::unique(Account::class, 'code')->ignore($account)],
            ...$this->translationRules('name'),
            'subtype' => ['required', Rule::enum(AccountSubtype::class), $this->subtypeMatchesParent(...)],
            'is_group' => ['boolean', $this->groupWithoutPostings(...)],
            'is_active' => ['boolean'],
            'currency_id' => ['nullable', 'integer', Rule::exists(Currency::class, 'id')],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'parent_id' => __('Parent account'),
            'code' => __('Code'),
            'subtype' => __('Account type'),
        ];
    }

    public function account(): ?Account
    {
        $account = $this->route('account');

        return $account instanceof Account ? $account : null;
    }

    protected function subtypeMatchesParent(string $attribute, mixed $value, Closure $fail): void
    {
        $parent = Account::query()->find($this->integer('parent_id') ?: null);
        $subtype = AccountSubtype::tryFrom((string) $value);

        if ($parent && $subtype && $subtype->type() !== $parent->type) {
            $fail(__('An account must be of the same type as its parent (:type).', ['type' => $parent->type->label()]));
        }
    }

    protected function parentIsNotDescendant(string $attribute, mixed $value, Closure $fail): void
    {
        $account = $this->account();
        $current = $value ? Account::query()->find((int) $value) : null;

        while ($account && $current) {
            if ($current->id === $account->id) {
                $fail(__('An account cannot be placed under itself or one of its children.'));

                return;
            }

            $current = $current->parent;
        }
    }

    protected function groupWithoutPostings(string $attribute, mixed $value, Closure $fail): void
    {
        $account = $this->account();

        if ($account && $this->boolean('is_group') && ! $account->is_group && $account->journalLines()->exists()) {
            $fail(__('An account with postings cannot become a group account.'));
        }

        if ($account && ! $this->boolean('is_group') && $account->is_group && $account->children()->exists()) {
            $fail(__('A group account with children must stay a group account.'));
        }
    }
}
