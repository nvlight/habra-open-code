<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CertCheck extends Command
{
    protected $signature = 'cert:check {--min-days=4 : Alert when fewer days remain}';

    protected $description = 'Check TLS certificate expiry on nginx and alert via error log (Telegram)';

    public function handle(): int
    {
        $minDays = max(1, (int) $this->option('min-days'));

        $cert = $this->fetchCertificate();

        if ($cert === null) {
            Log::error('TLS-сертификат: не удалось получить сертификат с nginx:443');

            return self::FAILURE;
        }

        $validTo = $cert['validTo_time_tm'];

        $expiresAt = mktime(
            (int) $validTo['tm_hour'],
            (int) $validTo['tm_min'],
            (int) $validTo['tm_sec'],
            (int) $validTo['tm_mon'] + 1,
            (int) $validTo['tm_mday'],
            (int) $validTo['tm_year'] + 1900
        );

        $daysLeft = (int) floor(($expiresAt - time()) / 86400);

        if ($daysLeft < 0) {
            Log::error("TLS-сертификат ИСТЁК {$daysLeft} дн. назад — сайт недоступен по HTTPS!");

            return self::FAILURE;
        }

        if ($daysLeft < $minDays) {
            Log::error("TLS-сертификат истекает через {$daysLeft} дн. (порог {$minDays}) — проверьте продление certbot");

            return self::FAILURE;
        }

        $this->info("Сертификат действителен ещё {$daysLeft} дн. (до ".date('Y-m-d', $expiresAt).')');

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchCertificate(): ?array
    {
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
                'SNI_enabled' => true,
            ],
        ]);

        $socket = @stream_socket_client('ssl://nginx:443', $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);

        if ($socket === false) {
            return null;
        }

        $params = stream_context_get_options($socket);

        fclose($socket);

        return $params['ssl']['peer_certificate'] ?? null ? openssl_x509_parse($params['ssl']['peer_certificate']) : null;
    }
}
