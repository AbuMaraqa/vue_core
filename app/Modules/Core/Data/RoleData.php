<?php

namespace App\Modules\Core\Data;

use Spatie\LaravelData\Data;

/**
 * The validated details of a role and the permissions it grants.
 */
class RoleData extends Data
{
    /**
     * @param  list<string>  $permissions
     */
    public function __construct(
        public string $name,
        public array $permissions = [],
    ) {}
}
