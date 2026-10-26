<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformAuthSession extends Model
{
    use HasFactory;

    public $timestamps = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'cen_hie_dependency_id',
        'laravel_session_id',
        'started_at',
        'ended_at',
        'end_reason',
    ];
}
