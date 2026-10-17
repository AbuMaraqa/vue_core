<?php

use App\Modules\Core\Models\TemporaryUpload;
use Illuminate\Support\Facades\Schedule;

Schedule::command('backup:clean')->daily()->at('01:00');
Schedule::command('backup:run')->daily()->at('01:30');
Schedule::command('activitylog:clean')->weekly();
Schedule::command('model:prune', ['--model' => [TemporaryUpload::class]])->hourly();
