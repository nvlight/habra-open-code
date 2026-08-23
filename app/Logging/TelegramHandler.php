<?php

namespace App\Logging;

use Illuminate\Support\Facades\Http;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Throwable;

final class TelegramHandler extends AbstractProcessingHandler
{
    private const MAX_MESSAGE_LENGTH = 4000;

    public function __construct(
        private readonly string $token,
        private readonly string $chatId,
        string $level = 'error',
    ) {
        parent::__construct($level);
    }

    protected function write(LogRecord $record): void
    {
        if ($this->token === '' || $this->chatId === '') {
            return;
        }

        try {
            Http::timeout(5)
                ->post("https://api.telegram.org/bot{$this->token}/sendMessage", [
                    'chat_id' => $this->chatId,
                    'text' => $this->render($record),
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]);
        } catch (Throwable) {
        }
    }

    private function render(LogRecord $record): string
    {
        $lines = [
            sprintf('<b>%s</b> · %s · %s', mb_strtoupper($record->level->getName()), e(config('app.name', 'Laravel')), e(config('app.env', 'production'))),
            sprintf('<b>Message:</b> %s', e(trim($record->message))),
            sprintf('<b>Time:</b> %s', $record->datetime->format('Y-m-d H:i:s')),
        ];

        if (! app()->runningInConsole()) {
            $lines[] = sprintf('<b>URL:</b> %s %s', request()->method(), e(request()->fullUrl()));
        }

        $exception = $record->context['exception'] ?? null;

        if ($exception instanceof Throwable) {
            $lines[] = sprintf('<b>Exception:</b> <code>%s</code>', e($exception::class));
            $lines[] = sprintf('<b>File:</b> <code>%s:%d</code>', e($exception->getFile()), $exception->getLine());
            $lines[] = sprintf('<pre>%s</pre>', e(mb_substr($exception->getTraceAsString(), 0, 1500)));
        } elseif ($record->context !== []) {
            $json = json_encode($record->context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            $lines[] = sprintf('<pre>%s</pre>', e(mb_substr((string) $json, 0, 1500)));
        }

        return mb_substr(implode("\n", $lines), 0, self::MAX_MESSAGE_LENGTH);
    }
}
