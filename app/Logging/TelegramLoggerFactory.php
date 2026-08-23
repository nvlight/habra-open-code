<?php

namespace App\Logging;

use Monolog\Logger;

final class TelegramLoggerFactory
{
    public function __invoke(array $config): Logger
    {
        return new Logger('telegram', [
            new TelegramHandler(
                token: (string) ($config['token'] ?? ''),
                chatId: (string) ($config['chat_id'] ?? ''),
                level: (string) ($config['level'] ?? 'error'),
            ),
        ]);
    }
}
