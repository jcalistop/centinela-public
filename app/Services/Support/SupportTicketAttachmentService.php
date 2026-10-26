<?php

namespace App\Services\Support;

use App\Models\SupportTicket;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SupportTicketAttachmentService
{
    private const STORAGE_PREFIX = 'support/tickets/';

    /**
     * @return array{path: string, name: string}
     */
    public function storeForTicket(SupportTicket $ticket, UploadedFile $file): array
    {
        $path = $file->store(
            self::STORAGE_PREFIX.$ticket->id,
            'local',
        );

        return [
            'path' => $path,
            'name' => $file->getClientOriginalName(),
        ];
    }

    /**
     * @return array{path: string, name: string}
     */
    public function resolveForDownload(SupportTicket $ticket): array
    {
        $attachment = $ticket->attachment;

        if (! is_array($attachment)) {
            abort(404);
        }

        $path = $attachment['path'] ?? null;
        $name = $attachment['name'] ?? 'adjunto';

        if (! is_string($path) || ! $this->isManagedPath($path) || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return [
            'path' => $path,
            'name' => is_string($name) && $name !== '' ? $name : basename($path),
        ];
    }

    public function isManagedPath(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        return Str::startsWith($normalized, self::STORAGE_PREFIX);
    }
}
