<?php

namespace App\Services\Cen;

use App\Models\CenCenter;
use App\Models\User;
use App\Support\Cen\CenCenterExportApiColumnMap;
use Illuminate\Database\Eloquent\Builder;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class CenCenterExcelExportService
{
    public function __construct(
        private readonly CenCenterBrowseService $browseService,
    ) {}

    public function downloadForUser(User $user, ?string $dataTypeScope = null): BinaryFileResponse
    {
        $fileName = date('YmdHi').'_establecimientos.xlsx';
        $tempPath = storage_path('temp/'.$fileName);

        if (! is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        $headers = CenCenterExportApiColumnMap::headerOrder();
        $writer = SimpleExcelWriter::create($tempPath);
        $writer->addHeader($headers);

        $this->scopedCentersQueryForExport($user)
            ->with(['cenAddress.cenCommune.cenProvince.cenRegion', 'cenHieDependency'])
            ->orderBy('nombre_corto')
            ->chunkById(200, function ($centers) use ($writer): void {
                foreach ($centers as $center) {
                    if (! $center instanceof CenCenter) {
                        continue;
                    }

                    $writer->addRow(CenCenterExportApiColumnMap::buildRow($center));
                }
            });

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }

    private function scopedCentersQueryForExport(User $user): Builder
    {
        return $this->browseService->scopedCentersQueryForBrowse($user);
    }
}
