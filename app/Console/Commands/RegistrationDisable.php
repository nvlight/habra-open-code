<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RegistrationDisable extends Command
{
    protected $signature = 'registration:disable';

    protected $description = 'Disable new user registration';

    public function handle(): int
    {
        Cache::forever('registration.disabled', true);

        $this->info('Регистрация новых пользователей отключена.');

        return self::SUCCESS;
    }
}
