<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RegistrationEnable extends Command
{
    protected $signature = 'registration:enable';

    protected $description = 'Enable new user registration';

    public function handle(): int
    {
        Cache::forget('registration.disabled');

        $this->info('Регистрация новых пользователей включена.');

        return self::SUCCESS;
    }
}
