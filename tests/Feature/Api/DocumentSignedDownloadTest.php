<?php

namespace Tests\Feature\Api;

use App\Models\CenHieDependency;
use App\Models\Document;
use App\Models\IndicatorType;
use App\Models\User;
use App\Models\Year;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class DocumentSignedDownloadTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private IndicatorType $type;

    private User $uploader;

    protected function setUp(): void
    {
        parent::setUp();

        config(['centinela.agent_document_url_ttl_minutes' => 10]);
        Storage::fake('private');

        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        CenHieDependency::unguarded(fn () => CenHieDependency::firstOrCreate(
            ['id' => 1],
            [
                'codigo' => 'SS01',
                'nombre' => 'Servicio Uno',
                'tipo_dependencia' => 'SERVICIO',
                'habilitado' => true,
            ]
        ));
        CenHieDependency::unguarded(fn () => CenHieDependency::firstOrCreate(
            ['id' => 2],
            [
                'codigo' => 'SS02',
                'nombre' => 'Servicio Dos',
                'tipo_dependencia' => 'SERVICIO',
                'habilitado' => true,
            ]
        ));

        $this->uploader = User::factory()->create([
            'email' => 'docs-signed@example.com',
            'name' => 'Uploader',
        ]);
        $this->uploader->forceFill(['cen_hie_dependency_id' => 1])->save();

        $this->type = IndicatorType::query()->firstOrCreate(
            ['route' => 'metas-18834'],
            [
                'clave_indicador' => 'M18834',
                'nombre_indicador' => 'Metas 18.834',
                'estado' => true,
                'orden' => 1,
            ]
        );
    }

    public function test_valid_signed_url_downloads_file_without_session(): void
    {
        $doc = $this->createDocument('documents/ok.pdf', 'ok.pdf', 'hola-mundo');

        $url = URL::temporarySignedRoute(
            'centinela.documents.download',
            now()->addMinutes(10),
            ['document' => $doc->id, 'ss' => 1, 'file_index' => 0]
        );

        $response = $this->get($url);
        $response->assertOk();
        $response->assertDownload('ok.pdf');
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $fileResponse = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $fileResponse);
        $this->assertSame('hola-mundo', file_get_contents($fileResponse->getFile()->getPathname()));
    }

    public function test_expired_or_tampered_signature_is_forbidden(): void
    {
        $doc = $this->createDocument('documents/exp.pdf', 'exp.pdf', 'exp');

        $url = URL::temporarySignedRoute(
            'centinela.documents.download',
            now()->addMinutes(10),
            ['document' => $doc->id, 'ss' => 1, 'file_index' => 0]
        );

        Carbon::setTestNow(now()->addMinutes(11));
        $this->get($url)->assertForbidden();
        Carbon::setTestNow();

        $fresh = URL::temporarySignedRoute(
            'centinela.documents.download',
            now()->addMinutes(10),
            ['document' => $doc->id, 'ss' => 1, 'file_index' => 0]
        );
        $tampered = $fresh.(str_contains($fresh, '?') ? '&' : '?').'x=1';
        $this->get($tampered)->assertForbidden();
    }

    public function test_cross_ss_signed_params_return_not_found(): void
    {
        $doc = $this->createDocument('documents/ss.pdf', 'ss.pdf', 'ss');

        $url = URL::temporarySignedRoute(
            'centinela.documents.download',
            now()->addMinutes(10),
            ['document' => $doc->id, 'ss' => 2, 'file_index' => 0]
        );

        $this->get($url)->assertNotFound();
    }

    public function test_missing_file_on_disk_returns_not_found(): void
    {
        $doc = $this->createDocument('documents/gone.pdf', 'gone.pdf', null);

        $url = URL::temporarySignedRoute(
            'centinela.documents.download',
            now()->addMinutes(10),
            ['document' => $doc->id, 'ss' => 1, 'file_index' => 0]
        );

        $this->get($url)->assertNotFound();
    }

    public function test_path_traversal_download_link_is_forbidden(): void
    {
        $id = DB::table('documents')->insertGetId([
            'nombre' => 'Traversal',
            'descripcion' => 'Desc',
            'autor' => 'Autor',
            'file' => json_encode([
                [
                    'download_link' => '../secrets/x.pdf',
                    'original_name' => 'x.pdf',
                ],
            ]),
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->type->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $this->uploader->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $url = URL::temporarySignedRoute(
            'centinela.documents.download',
            now()->addMinutes(10),
            ['document' => $id, 'ss' => 1, 'file_index' => 0]
        );

        $this->get($url)->assertForbidden();
    }

    private function createDocument(string $relativePath, string $originalName, ?string $contents): Document
    {
        if ($contents !== null) {
            Storage::disk('private')->put($relativePath, $contents);
        }

        $id = DB::table('documents')->insertGetId([
            'nombre' => 'Doc firmado',
            'descripcion' => 'Desc',
            'autor' => 'Autor',
            'file' => json_encode([
                [
                    'download_link' => $relativePath,
                    'original_name' => $originalName,
                ],
            ]),
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->type->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $this->uploader->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Document::query()->findOrFail($id);
    }
}
