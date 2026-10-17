<?php

use App\Modules\Core\Providers\CoreServiceProvider;
use App\Modules\Foundation\Providers\FoundationServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    CoreServiceProvider::class,
    FoundationServiceProvider::class,
];
