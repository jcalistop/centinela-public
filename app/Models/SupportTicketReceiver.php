<?php

namespace App\Models;

use App\Enums\SupportTicketArea;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketReceiver extends Model
{
    public const SCOPE_GLOBAL = 'global';

    public const SCOPE_SS = 'ss';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'scope',
        'cen_hie_dependency_id',
        'area',
        'user_id',
        'is_primary',
        'enabled',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_primary' => 'boolean',
        'enabled' => 'boolean',
        'area' => SupportTicketArea::class,
    ];

    public function cenHieDependency(): BelongsTo
    {
        return $this->belongsTo(CenHieDependency::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
