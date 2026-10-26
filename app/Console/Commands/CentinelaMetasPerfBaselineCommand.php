<?php

namespace App\Console\Commands;

use App\Mail\MetasPerfBaselineReportMail;
use App\Models\User;
use App\Models\Year;
use App\Services\Metas\PerfBaseline\MetasPerfBaselineRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class CentinelaMetasPerfBaselineCommand extends Command
{
    protected $signature = 'centinela:metas:perf-baseline
                            {--user_id=1 : Usuario actor (default admin Reloncaví)}
                            {--year= : Año calendario (ano); default Year::getDefaultYear()}
                            {--cen_hie_dependency_id= : SS; default SS del usuario}
                            {--scenarios= : Lista separada por comas (catalog,hub,results_summary)}
                            {--runs=3 : Corridas por escenario (cold)}
                            {--warm : Marca corridas como warm en el informe (sin Cache::flush)}
                            {--output= : Subruta bajo storage/app (default reports/metas-perf-baseline)}
                            {--max-seconds= : Límite de pared; fail-soft parcial}
                            {--no-mail : No encolar correo}';

    protected $description = 'Baseline de rendimiento Metas (Compass): informe en storage + correo opcional';

    public function handle(MetasPerfBaselineRunner $runner): int
    {
        $userId = (int) $this->option('user_id');
        $user = User::query()->find($userId);
        if (! $user instanceof User) {
            $this->error("Usuario id={$userId} no existe.");

            return self::FAILURE;
        }

        $ssOption = $this->option('cen_hie_dependency_id');
        $ssId = $ssOption !== null && $ssOption !== ''
            ? (int) $ssOption
            : (int) ($user->cen_hie_dependency_id ?? 0);

        if ($ssId < 1) {
            $this->error("Usuario id={$userId} no tiene cen_hie_dependency_id usable.");

            return self::FAILURE;
        }

        try {
            $year = $this->resolveYear();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $scenariosOption = $this->option('scenarios');
        $scenarios = is_string($scenariosOption) && trim($scenariosOption) !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $scenariosOption))))
            : MetasPerfBaselineRunner::DEFAULT_SCENARIOS;

        $runs = max(1, (int) $this->option('runs'));
        $warm = (bool) $this->option('warm');
        $output = $this->option('output');
        $outputRelative = is_string($output) && $output !== '' ? $output : null;
        $maxSecondsOpt = $this->option('max-seconds');
        $maxSeconds = $maxSecondsOpt !== null && $maxSecondsOpt !== '' ? max(1, (int) $maxSecondsOpt) : null;
        $noMail = (bool) $this->option('no-mail');

        $this->info(sprintf(
            'Baseline Metas: user=%d SS=%d year=%d scenarios=%s runs=%d',
            $user->id,
            $ssId,
            $year->ano,
            implode(',', $scenarios),
            $runs
        ));

        try {
            $report = $runner->run(
                user: $user,
                year: $year,
                ssId: $ssId,
                scenarios: $scenarios,
                runs: $runs,
                warm: $warm,
                maxSeconds: $maxSeconds,
                outputRelative: $outputRelative,
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('Markdown: '.$report['markdown_path']);
        $this->line('JSON: '.$report['json_path']);
        if ($report['partial']) {
            $this->warn('Corrida parcial (max-seconds). Omitidos: '.implode(', ', $report['skipped']));
        }

        foreach ($report['findings'] as $finding) {
            $this->line(sprintf(
                '[%s] %s — %s',
                $finding['label'],
                $finding['scenario'] ?? 'global',
                $finding['note']
            ));
        }

        if ($noMail) {
            $this->comment('Correo omitido (--no-mail).');

            return self::SUCCESS;
        }

        $recipient = trim((string) config('centinela.scheduler.alert_email', ''));
        if ($recipient === '') {
            $this->warn('Sin correo: notifications.scheduler_alert_email / centinela.scheduler.alert_email vacío.');

            return self::SUCCESS;
        }

        try {
            Mail::to($recipient)->queue(new MetasPerfBaselineReportMail($report));
            $this->info("Correo encolado a {$recipient}.");
        } catch (Throwable $e) {
            Log::warning('metas-perf-baseline no pudo encolar correo', [
                'message' => $e->getMessage(),
            ]);
            $this->warn('No se pudo encolar el correo: '.$e->getMessage().' (informe en storage OK).');
        }

        return self::SUCCESS;
    }

    private function resolveYear(): Year
    {
        $yearOpt = $this->option('year');
        if ($yearOpt !== null && $yearOpt !== '') {
            $ano = (int) $yearOpt;
            $year = Year::query()->where('ano', $ano)->first();
            if (! $year instanceof Year) {
                throw new \RuntimeException("No existe Year con ano={$ano}.");
            }

            return $year;
        }

        return Year::getDefaultYear();
    }
}
