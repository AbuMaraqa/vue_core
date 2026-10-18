<?php

namespace App\Modules\Core\Http\Requests\Roles\Concerns;

use Closure;

trait ValidatesGrantablePermissions
{
    /**
     * A permission can only be put in a role by someone who holds it, so
     * editing roles never raises anyone's access above the editor's.
     */
    protected function grantablePermission(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && ! $this->user()->canGrantPermissions([$value])) {
            $fail(__('You cannot grant the :permission permission: you do not hold it.', ['permission' => __($value)]));
        }
    }
}
