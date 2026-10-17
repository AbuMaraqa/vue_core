<?php

namespace App\Modules\Core\Data;

use Spatie\LaravelData\Data;

/**
 * The validated details of a user managed from the admin panel.
 */
class UserData extends Data
{
    /**
     * @param  list<string>  $roles
     */
    public function __construct(
        public string $name,
        public string $email,
        public array $roles = [],
        public ?string $password = null,
    ) {}
}
