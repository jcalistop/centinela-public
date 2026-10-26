<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SecurityAuditCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'security:audit-check {--hours= : Horas hacia atrás para analizar} {--threshold= : Umbral de intentos por IP}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analiza intentos de login fallidos y alerta por actividad sospechosa';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $logPath = storage_path('logs/failed-logins.log');

        if (! file_exists($logPath)) {
            $this->line('Sin registro de intentos fallidos (archivo aún no creado).');

            return Command::SUCCESS;
        }

        $hours = (int) ($this->option('hours') ?: config('centinela.security.failed_login_lookback_hours', 24));
        $threshold = (int) ($this->option('threshold') ?: config('centinela.security.failed_login_threshold', 10));

        $this->pruneLog($logPath);

        [$attemptsTotal, $suspiciousIps] = $this->parseLog($logPath, $hours, $threshold);

        $this->line('Intentos fallidos analizados: '.$attemptsTotal);
        $this->line('IPs sospechosas detectadas: '.count($suspiciousIps));

        if (empty($suspiciousIps)) {
            Log::info('Security audit sin actividad sospechosa', [
                'hours' => $hours,
                'threshold' => $threshold,
                'attempts_total' => $attemptsTotal,
            ]);

            return Command::SUCCESS;
        }

        $lines = implode("\n", array_map(
            fn ($ip, $count) => "- {$ip} => {$count} intentos",
            array_keys($suspiciousIps),
            $suspiciousIps
        ));

        $this->warn("Actividad sospechosa detectada:\n{$lines}");

        foreach ($this->getRecipients() as $recipient) {
            Mail::raw(
                "ALERTA DE SEGURIDAD\n\n".
                    'Ambiente: '.config('app.env')."\n".
                    'Servidor: '.gethostname()."\n".
                    "Ventana analizada: últimas {$hours} horas\n".
                    "Umbral por IP: {$threshold}\n".
                    "Intentos fallidos totales: {$attemptsTotal}\n\n".
                    "IPs sospechosas:\n{$lines}\n\n".
                    'Hora: '.now()->format('Y-m-d H:i:s'),
                function ($message) use ($recipient): void {
                    $message->to($recipient)
                        ->subject('ALERTA: Actividad sospechosa de login - '.config('app.name'));
                }
            );
        }

        Log::warning('Security audit detectó IPs sospechosas', [
            'hours' => $hours,
            'threshold' => $threshold,
            'attempts_total' => $attemptsTotal,
            'suspicious_ips' => $suspiciousIps,
        ]);

        return Command::FAILURE;
    }

    private function pruneLog(string $logPath): void
    {
        $retentionDays = (int) config('centinela.security.failed_login_retention_days', 30);
        $cutoff = now()->subDays($retentionDays);

        $handle = fopen($logPath, 'r');
        if ($handle === false) {
            return;
        }

        $kept = [];
        $pruned = 0;

        while (($line = fgets($handle)) !== false) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            $entry = json_decode($trimmed, true);
            if (! is_array($entry) || empty($entry['timestamp'])) {
                $kept[] = $trimmed;

                continue;
            }

            try {
                $ts = Carbon::parse($entry['timestamp']);
            } catch (\Throwable) {
                $kept[] = $trimmed;

                continue;
            }

            if ($ts->gte($cutoff)) {
                $kept[] = $trimmed;
            } else {
                $pruned++;
            }
        }

        fclose($handle);

        if ($pruned === 0) {
            return;
        }

        file_put_contents($logPath, implode("\n", $kept).(count($kept) > 0 ? "\n" : ''), LOCK_EX);
        $this->line("Entradas antiguas eliminadas del log: {$pruned} (retención: {$retentionDays} días)");
    }

    /**
     * @return array{int, array<string, int>}
     */
    private function parseLog(string $logPath, int $hours, int $threshold): array
    {
        $since = now()->subHours($hours);
        $attemptsTotal = 0;
        $ipCounts = [];

        $handle = fopen($logPath, 'r');
        if ($handle === false) {
            return [0, []];
        }

        while (($line = fgets($handle)) !== false) {
            $entry = json_decode(trim($line), true);
            if (! is_array($entry) || empty($entry['timestamp'])) {
                continue;
            }

            try {
                $ts = Carbon::parse($entry['timestamp']);
            } catch (\Throwable) {
                continue;
            }

            if ($ts->lt($since)) {
                continue;
            }

            $attemptsTotal++;
            $ip = $entry['ip_address'] ?? null;
            if (is_string($ip) && $ip !== '') {
                $ipCounts[$ip] = ($ipCounts[$ip] ?? 0) + 1;
            }
        }

        fclose($handle);

        $suspicious = array_filter($ipCounts, fn ($count) => $count >= $threshold);
        arsort($suspicious);

        return [$attemptsTotal, array_slice($suspicious, 0, 10, true)];
    }

    private function getRecipients(): array
    {
        $configured = config('centinela.security.alert_email_to')
            ?: config('centinela.scheduler.alert_email')
            ?: config('services.security.alert_email_to')
            ?: config('services.scheduler.alert_email_to', '');
        if (is_array($configured)) {
            return array_values(array_filter($configured));
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $configured))));
    }
}
