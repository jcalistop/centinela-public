<?php

namespace App\Http\Controllers;

use App\Enums\Comges\EvaluacionTipo;
use App\Helpers\Helper;
use App\Models\ComAction;
use App\Models\ComEvaluation;
use App\Models\ComIndicator;
use App\Models\ComIndicatorCutoff;
use App\Models\ComIndicatorSetting;
use App\Models\ComSensitivityDependency;
use App\Models\ComSensitivityTable;
use App\Models\User;
use App\Models\Year;
use App\Services\ComgesResultsService;
use App\Services\Github\GithubReleasesService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Spatie\SimpleExcel\SimpleExcelWriter;
use ZipArchive;

class ComgesController extends Controller
{
    public function __construct(
        private readonly GithubReleasesService $githubReleasesService,
    ) {}

    public function configurarIndicador(int $id): RedirectResponse
    {
        $comIndicatorSetting = ComIndicatorSetting::firstOrCreate([
            'cen_hie_dependency_id' => $this->authenticatedUser()->cen_hie_dependency_id,
            'com_indicator_id' => $id,
        ]);

        return redirect()->route('voyager.configurar-indicador-comges.edit', $comIndicatorSetting->id);
    }

    public function habilitaMeta(int $com_sensitivity_table_id)
    {
        $comSensitivityTable = ComSensitivityTable::find($com_sensitivity_table_id);

        $comSensitivityDependency = ComSensitivityDependency::firstOrNew([
            'com_sensitivity_table_id' => $com_sensitivity_table_id,
            'com_action_id' => $comSensitivityTable->com_action_id,
            'cen_hie_dependency_id' => $this->authenticatedUser()->cen_hie_dependency_id,
        ]);
        $comSensitivityDependency->save();

        return redirect()->route('voyager.indicadores-comges.show', $comSensitivityTable->comAction->comIndicatorCutoff->com_indicator_id);
    }

    public function verificablesDownload(Request $request)
    {
        $currentYear = Helper::showCurrentYear();
        $currentDependencyId = $this->authenticatedUser()->cen_hie_dependency_id;

        $comIndicatorCutoff = ComIndicatorCutoff::with([
            'cutoff',
            'comIndicator.comCommitment',
            'comActions.comVerifiableDocuments' => function ($query) use ($currentDependencyId) {
                $query->where('cen_hie_dependency_id', $currentDependencyId);
            },
        ])->find($request->com_indicator_cutoff_id);
        $this->authorize('current-add-edit', [$comIndicatorCutoff->comActions->first()->id ?? null, 'AUTOEVALUACION']);

        // Nombre del archivo ZIP a generar
        $nombreArchivoZip = date('YmdHm').'_Verificables_COMGES.zip';

        // Crear una nueva instancia de ZipArchive
        $zip = new ZipArchive;

        // Ruta donde se almacenará el archivo ZIP temporalmente
        $rutaTemporalZip = storage_path('temp/'.$nombreArchivoZip);

        // Abrir el archivo ZIP para escritura
        if ($zip->open($rutaTemporalZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $disk = $this->voyagerStorageDisk();

            // Recorrer cada elemento y agregarlo al archivo ZIP
            foreach ($comIndicatorCutoff->comActions as $comAction) {
                $comVerifiableDocuments = $comAction->comVerifiableDocuments;
                foreach ($comVerifiableDocuments as $comVerifiableDocument) {
                    foreach (json_decode($comVerifiableDocument->archivos) as $archivo) {
                        $tarjet_path =
                            $currentYear->ano.'/'.
                            $comIndicatorCutoff->cutoff->clave->value.'/'.
                            $comIndicatorCutoff->comIndicator->comCommitment->clave.'/'.
                            $comIndicatorCutoff->comIndicator->clave.'/'.
                            'ACCION '.$comAction->numero.'/';
                        // Agregar el archivo al archivo ZIP con su ruta específica
                        if ($disk->exists($archivo->download_link)) {
                            $zip->addFile(
                                $disk->path($archivo->download_link),
                                $tarjet_path.$archivo->original_name
                            );
                        } else {
                            $mensaje = 'Error al descargar el archivo '.basename($archivo->original_name).' en la siguiente ruta';
                        }
                    }
                }
            }
            if ($zip->numFiles == 0) {
                $mensaje = 'No existen archivos para descargar.';
            }
            // Cerrar el archivo ZIP
            $zip->close();
            // Descargar el archivo ZIP
            if (! isset($mensaje)) {
                return response()->download($rutaTemporalZip)->deleteFileAfterSend(true);
            }

            $this->unlinkStorageTempIfExists($rutaTemporalZip);

            return back()->with([
                'message' => $mensaje,
                'alert-type' => 'error',
            ]);
        }

        $this->unlinkStorageTempIfExists($rutaTemporalZip);

        return back()->with([
            'message' => 'No se pudo crear el archivo comprimido ZIP, comuníquese con el Administrador de la Plataforma',
            'alert-type' => 'error',
        ]);
    }

    public function massiveDownload(Request $request)
    {
        $currentYear = Helper::showCurrentYear();
        $user = $this->authenticatedUser();
        $currentDependencyId = $user->cen_hie_dependency_id;

        $comIndicatorCutoffs = ComIndicatorCutoff::where('cutoff_id', $request->cutoff_id)
            ->whereHas('comIndicator.comCommitment', function ($query) use ($currentYear) {
                $query->where('year_id', $currentYear->id);
            })
            ->with([
                'cutoff',
                'comIndicator.comCommitment',
                'comActions.comVerifiableDocuments' => function ($query) use ($currentDependencyId) {
                    $query->where('cen_hie_dependency_id', $currentDependencyId);
                },
            ])
            ->get();

        if (! $user->hasRole(['control-gestion', 'admin'])) {
            return abort(403, 'No estás autorizado para realizar esta acción');
        }

        // Nombre del archivo ZIP a generar
        $nombreArchivoZip = date('YmdHm').'_Verificables_COMGES.zip';

        // Crear una nueva instancia de ZipArchive
        $zip = new ZipArchive;

        // Ruta donde se almacenará el archivo ZIP temporalmente
        $rutaTemporalZip = storage_path('temp/'.$nombreArchivoZip);

        // Abrir el archivo ZIP para escritura
        if ($zip->open($rutaTemporalZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $disk = $this->voyagerStorageDisk();

            // Recorrer cada elemento y agregarlo al archivo ZIP
            foreach ($comIndicatorCutoffs as $comIndicatorCutoff) {
                foreach ($comIndicatorCutoff->comActions as $comAction) {
                    $comVerifiableDocuments = $comAction->comVerifiableDocuments;
                    foreach ($comVerifiableDocuments as $comVerifiableDocument) {
                        foreach (json_decode($comVerifiableDocument->archivos) as $archivo) {
                            $tarjet_path =
                                $currentYear->ano.'/'.
                                $comIndicatorCutoff->cutoff->clave->value.'/'.
                                $comIndicatorCutoff->comIndicator->comCommitment->clave.'/'.
                                $comIndicatorCutoff->comIndicator->clave.'/'.
                                'ACCION '.$comAction->numero.'/';
                            // Agregar el archivo al archivo ZIP con su ruta específica
                            if ($disk->exists($archivo->download_link)) {
                                $zip->addFile(
                                    $disk->path($archivo->download_link),
                                    $tarjet_path.$archivo->original_name
                                );
                            } else {
                                $mensaje = 'Error al descargar el archivo '.basename($archivo->original_name).' en la siguiente ruta '.$archivo->download_link;
                            }
                        }
                    }
                }
            }
            if ($zip->numFiles == 0) {
                $mensaje = 'No existen archivos para descargar.';
            }
            // Cerrar el archivo ZIP
            $zip->close();
            // Descargar el archivo ZIP
            if (! isset($mensaje)) {
                return response()->download($rutaTemporalZip)->deleteFileAfterSend(true);
            }

            $this->unlinkStorageTempIfExists($rutaTemporalZip);

            return back()->with([
                'message' => $mensaje,
                'alert-type' => 'error',
            ]);
        }

        $this->unlinkStorageTempIfExists($rutaTemporalZip);

        return back()->with([
            'message' => 'No se pudo crear el archivo comprimido ZIP, comuníquese con el Administrador de la Plataforma',
            'alert-type' => 'error',
        ]);
    }

    public function massiveDateCutoff(Request $request)
    {
        $user = $this->authenticatedUser();
        if (! $user->hasRole(['control-gestion', 'admin'])) {
            return abort(403, 'No estás autorizado para realizar esta acción');
        }

        try {
            $comIndicatorsId = ComIndicator::whereHas('comCommitment', function ($query) {
                $query->where('year_id', Helper::showCurrentYear()->id);
            })->pluck('id');

            $comIndicatorsIdArray = $comIndicatorsId->map(function ($id) use ($request, $user) {
                return [
                    'cen_hie_dependency_id' => $user->cen_hie_dependency_id,
                    'com_indicator_id' => $id,
                    'fecha_apertura' => $request->fecha_apertura,
                    'fecha_cierre' => $request->fecha_cierre,
                ];
            })->toArray();

            ComIndicatorSetting::upsert($comIndicatorsIdArray, ['cen_hie_dependency_id', 'com_indicator_id'], ['fecha_apertura', 'fecha_cierre']);

            return redirect()->back()->with([
                'message' => 'Fechas de apertura y cierre actualizadas',
                'alert-type' => 'success',
            ]);
        } catch (\Throwable $e) {
            return redirect()->back()->with([
                'message' => 'Error al intentar acturlizar las fechas: '.$e->getMessage(),
                'alert-type' => 'error',
            ]);
        }
    }

    public function massiveToggleEditableTabs(Request $request)
    {
        $user = $this->authenticatedUser();
        if (! $user->hasRole(['control-gestion', 'admin'])) {
            return abort(403, 'No estás autorizado para realizar esta acción');
        }

        $validated = $request->validate([
            'cutoff_id' => 'required|exists:cutoffs,id',
            'mode' => 'required|in:enable,disable',
        ]);

        $cutoffId = (int) $validated['cutoff_id'];
        $mode = (string) $validated['mode'];

        $currentYear = Helper::showCurrentYear();

        $indicatorCutoffs = ComIndicatorCutoff::query()
            ->where('cutoff_id', $cutoffId)
            ->whereHas('comIndicator.comCommitment', function ($query) use ($currentYear) {
                $query->where('year_id', $currentYear->id);
            })
            ->get(['id', 'com_indicator_id']);

        $updated = 0;
        foreach ($indicatorCutoffs as $indicatorCutoff) {
            $setting = ComIndicatorSetting::firstOrCreate([
                'cen_hie_dependency_id' => $user->cen_hie_dependency_id,
                'com_indicator_id' => $indicatorCutoff->com_indicator_id,
            ]);

            $current = json_decode($setting->pestanas_editables ?? '[]', true);
            $current = is_array($current) ? $current : [];

            $tabId = (int) $indicatorCutoff->id;
            if ($mode === 'enable') {
                $current[] = $tabId;
                $current = array_values(array_unique(array_map('intval', $current)));
            } else {
                $current = array_values(array_filter(
                    array_map('intval', $current),
                    static fn (int $id): bool => $id !== $tabId,
                ));
            }

            $setting->pestanas_editables = json_encode($current);
            $setting->save();
            $updated++;
        }

        $accion = $mode === 'enable' ? 'activadas' : 'desactivadas';

        return redirect()->back()->with([
            'message' => "Pestañas {$accion} para {$updated} indicadores del corte seleccionado.",
            'alert-type' => 'success',
        ]);
    }

    public function excelDownload()
    {
        $currentYear = Helper::showCurrentYear();
        $currentDependencyId = $this->authenticatedUser()->cen_hie_dependency_id;

        $comActions = ComAction::whereHas('comIndicatorCutoff.comIndicator.comCommitment', function ($query) {
            $query->where('year_id', Helper::showCurrentYear()->id);
        })
            ->with([
                'comIndicatorCutoff.comIndicator.comCommitment',
                'comIndicatorCutoff.cutoff',
                'comEvaluations' => function ($query) use ($currentDependencyId) {
                    $query->where('cen_hie_dependency_id', $currentDependencyId);
                },
            ])
            ->get();

        $nombreArchivo = date('YmdHi').'_Evaluaciones_COMGES.xlsx'; // Corrección en el formato de fecha
        $rutaTemporal = storage_path('temp/'.$nombreArchivo);
        $columnNames = [
            'Compromiso',
            'Indicador',
            'Pond. indicador',
            'Corte',
            'Pond. corte',
            'Acción',
            'Pond. acción',
            'Numerador',
            'Denominador',
            'Resultado',
            'Cumplimiento',
        ];

        $writer = SimpleExcelWriter::create($rutaTemporal);

        foreach (['AUTOEVALUACION', 'PRELIMINAR', 'FINAL'] as $loop => $etapa) {
            if (! $loop) {
                $writer->nameCurrentSheet($etapa);
            } else {
                $writer->addNewSheetAndMakeItCurrent($etapa);
            }
            $writer->addHeader($columnNames);

            foreach ($comActions as $comAction) {
                $comEvaluation = $comAction->comEvaluations
                    ->where('tipo_evaluacion', EvaluacionTipo::from($etapa))
                    ->firstWhere('cen_hie_dependency_id', $currentDependencyId);

                $writer->addRow([
                    $comAction->comIndicatorCutoff->comIndicator->comCommitment->clave,
                    $comAction->comIndicatorCutoff->comIndicator->clave,
                    number_format($comAction->comIndicatorCutoff->comIndicator->ponderacion_indicador, 2, ','),
                    $comAction->comIndicatorCutoff->cutoff->clave->value,
                    number_format($comAction->comIndicatorCutoff->ponderacion_corte, 2, ','),
                    $comAction->numero,
                    number_format($comAction->ponderacion_accion, 2, ','),
                    number_format(optional($comEvaluation)->numerador, 2, ','),
                    number_format(optional($comEvaluation)->denominador, 2, ','),
                    number_format(optional($comEvaluation)->resultado, 2, ','),
                    number_format(optional($comEvaluation)->cumplimiento, 2, ','),
                ]);
            }
        }

        return response()->download($rutaTemporal)->deleteFileAfterSend(true);
    }

    public function showReleases()
    {
        $page = $this->githubReleasesService->getPageData();

        return view('centinela.acerca-de.releases', [
            'releases' => $page->releases,
            'releasesStale' => $page->fromStaleCache,
            'releasesSyncedAt' => $page->syncedAt,
            'releasesUnavailable' => $page->unavailable,
            'deployedVersion' => $page->deployedVersion,
            'deployedAt' => $page->deployedAt,
            'appVersion' => (string) config('app.version', ''),
        ]);
    }

    public function clearAutoEvaluations(Request $request)
    {
        $user = $this->authenticatedUser();
        if (! $user->hasRole(['control-gestion', 'admin'])) {
            return abort(403, 'No estás autorizado para realizar esta acción');
        }

        $request->validate([
            'cutoff_id' => 'required|exists:cutoffs,id',
        ]);

        try {
            $query = ComEvaluation::whereHas('comAction.comIndicatorCutoff', function ($query) use ($request) {
                $query->where('cutoff_id', $request->cutoff_id)
                    ->whereHas('comIndicator.comCommitment', function ($query2) {
                        $query2->where('year_id', Helper::showCurrentYear()->id);
                    });
            })
                ->where('cen_hie_dependency_id', $user->cen_hie_dependency_id)
                ->where('tipo_evaluacion', 'AUTOEVALUACION');

            $affectedRows = (clone $query)->count();

            // Eliminar completamente los registros filtrados
            $query->delete();

            $currentYear = Helper::showCurrentYear();
            if ($currentYear instanceof Year && $affectedRows > 0) {
                ComgesResultsService::forgetCachedGlobalResults(
                    $user->cen_hie_dependency_id,
                    $currentYear->id,
                );
            }

            return redirect()->back()->with([
                'message' => "Se eliminaron exitosamente {$affectedRows} autoevaluaciones del corte seleccionado",
                'alert-type' => 'success',
            ]);
        } catch (\Throwable $e) {
            return redirect()->back()->with([
                'message' => 'Error al intentar limpiar las autoevaluaciones: '.$e->getMessage(),
                'alert-type' => 'error',
            ]);
        }
    }

    private function authenticatedUser(): User
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }

    private function voyagerStorageDisk(): FilesystemAdapter
    {
        $disk = Storage::disk((string) config('voyager.storage.disk'));
        if (! $disk instanceof FilesystemAdapter) {
            throw new \RuntimeException('El disco de almacenamiento Voyager no admite rutas de archivo local.');
        }

        return $disk;
    }

    private function unlinkStorageTempIfExists(string $absolutePath): void
    {
        if (is_file($absolutePath)) {
            unlink($absolutePath);
        }
    }
}
