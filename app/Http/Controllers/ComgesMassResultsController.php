<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Jobs\Comges\ImportComgesEvaluationsJob;
use App\Models\ComAction;
use App\Models\ComEvaluation;
use App\Models\User;
use App\Models\Year;
use App\Support\Upload\UploadSizeLimit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Spatie\SimpleExcel\SimpleExcelWriter;

class ComgesMassResultsController extends Controller
{
    private const MIN_YEAR = 2025;

    public function index(Request $request)
    {
        $user = $this->authenticatedUser();
        $this->authorizeRole($user);

        $currentYear = Helper::showCurrentYear();
        $isAvailable = $currentYear instanceof Year && (int) $currentYear->ano >= self::MIN_YEAR;

        $tipoEvaluacion = $request->string('tipo_evaluacion')->toString() ?: 'AUTOEVALUACION';

        if (! in_array($tipoEvaluacion, ['AUTOEVALUACION', 'PRELIMINAR', 'FINAL'], true)) {
            $tipoEvaluacion = 'AUTOEVALUACION';
        }

        return view('centinela.comges.carga-masiva-resultados', [
            'tipoEvaluacion' => $tipoEvaluacion,
            'isAvailable' => $isAvailable,
            'currentYearLabel' => (string) ($currentYear instanceof Year ? $currentYear->ano : ''),
        ]);
    }

    public function downloadTemplate(Request $request)
    {
        $user = $this->authenticatedUser();
        $this->authorizeRole($user);

        $validated = $request->validate([
            'tipo_evaluacion' => ['required', Rule::in(['AUTOEVALUACION', 'PRELIMINAR', 'FINAL'])],
        ]);

        $tipoEvaluacion = (string) $validated['tipo_evaluacion'];

        $currentYear = Helper::showCurrentYear();
        if (! $this->isAvailableForYear($currentYear)) {
            return redirect()
                ->route('comges.carga-masiva-resultados.index', ['tipo_evaluacion' => $tipoEvaluacion])
                ->with([
                    'message' => 'La carga/descarga masiva de resultados COMGES está disponible solo desde el año '.self::MIN_YEAR.'.',
                    'alert-type' => 'warning',
                ]);
        }

        $dependencyId = $user->cen_hie_dependency_id;
        $dependencyName = (string) optional($user->cenHieDependency)->nombre;
        $yearLabel = (string) ($currentYear instanceof Year ? $currentYear->ano : '');

        $path = storage_path('temp/Comges_Resultados_'.$tipoEvaluacion.'_'.date('YmdHi').'.xlsx');

        $writer = SimpleExcelWriter::create($path);
        $writer->nameCurrentSheet('Resultados');
        $writer->addHeader([
            'servicio_salud',
            'ano',
            'tipo_evaluacion',
            'com_action_id',
            'compromiso',
            'indicador',
            'corte',
            'accion',
            'numerador',
            'denominador',
        ]);

        $comActionsQuery = ComAction::query()
            ->whereHas('comIndicatorCutoff.comIndicator.comCommitment', function ($query) use ($currentYear): void {
                $query->where('year_id', $currentYear->id);
            })
            ->whereHas('comIndicatorCutoff.cutoff', function ($query): void {
                $query->where('clave', '!=', 'APELACION');
            })
            ->with([
                'comIndicatorCutoff.cutoff',
                'comIndicatorCutoff.comIndicator.comCommitment',
                'comEvaluations' => function ($query) use ($dependencyId, $tipoEvaluacion): void {
                    $query->where('cen_hie_dependency_id', $dependencyId)
                        ->where('tipo_evaluacion', $tipoEvaluacion);
                },
            ]);

        if (in_array($tipoEvaluacion, ['PRELIMINAR', 'FINAL'], true)) {
            $comActionsQuery->whereHas('comIndicatorCutoff', function ($query): void {
                $query->where('evaluacion_local', 0);
            });
        }

        $comActions = $comActionsQuery->get();

        foreach ($comActions as $comAction) {
            /** @var ?ComEvaluation $evaluation */
            $evaluation = $comAction->comEvaluations->first();

            $writer->addRow([
                'servicio_salud' => $dependencyName,
                'ano' => $yearLabel,
                'tipo_evaluacion' => $tipoEvaluacion,
                'com_action_id' => $comAction->id,
                'compromiso' => (string) optional(optional(optional($comAction->comIndicatorCutoff)->comIndicator)->comCommitment)->clave,
                'indicador' => (string) optional(optional($comAction->comIndicatorCutoff)->comIndicator)->clave,
                'corte' => (string) (optional(optional($comAction->comIndicatorCutoff)->cutoff)->nombre_corte
                    ?? optional(optional($comAction->comIndicatorCutoff)->cutoff)->clave),
                'accion' => (string) $comAction->numero,
                'numerador' => $evaluation?->numerador,
                'denominador' => $evaluation?->denominador,
            ]);
        }

        return response()->download($path)->deleteFileAfterSend(true);
    }

    public function import(Request $request)
    {
        $user = $this->authenticatedUser();
        $this->authorizeRole($user);

        $validated = $request->validate([
            'tipo_evaluacion' => ['required', Rule::in(['AUTOEVALUACION', 'PRELIMINAR', 'FINAL'])],
            'archivo' => ['required', 'file', 'mimes:xlsx', 'max:'.UploadSizeLimit::importSpreadsheetMaxKilobytes()],
        ]);

        $tipoEvaluacion = (string) $validated['tipo_evaluacion'];
        $currentYear = Helper::showCurrentYear();
        if (! $this->isAvailableForYear($currentYear)) {
            return redirect()
                ->route('comges.carga-masiva-resultados.index', ['tipo_evaluacion' => $tipoEvaluacion])
                ->with([
                    'message' => 'La carga/descarga masiva de resultados COMGES está disponible solo desde el año '.self::MIN_YEAR.'.',
                    'alert-type' => 'warning',
                ]);
        }

        $path = $request->file('archivo')->store('temp');

        ImportComgesEvaluationsJob::dispatch(
            storedPath: (string) $path,
            tipoEvaluacion: $tipoEvaluacion,
            userId: $user->id,
            cenHieDependencyId: $user->cen_hie_dependency_id,
            yearId: $currentYear->id,
        );

        return redirect()
            ->route('comges.carga-masiva-resultados.index', ['tipo_evaluacion' => $tipoEvaluacion])
            ->with([
                'message' => 'Importación enviada a cola. Recibirás un correo con el resultado del proceso.',
                'alert-type' => 'success',
            ]);
    }

    private function authenticatedUser(): User
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }

    private function authorizeRole(User $user): void
    {
        if (! $user->hasRole(['control-gestion', 'admin'])) {
            abort(403);
        }
    }

    private function isAvailableForYear(?Year $year): bool
    {
        return $year instanceof Year && (int) $year->ano >= self::MIN_YEAR;
    }
}
