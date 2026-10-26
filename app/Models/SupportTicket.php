<?php

namespace App\Models;

use App\Enums\SupportTicketArea;
use App\Enums\SupportTicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class SupportTicket extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'subject',
        'body',
        'status',
        'area',
        'attachment',
        'cen_hie_dependency_id',
        'created_by_user_id',
        'assigned_to_user_id',
        'indicator_type_id',
        'year_id',
        'github_issue_number',
        'github_issue_url',
        'github_linked_at',
        'github_linked_by_user_id',
        'resolved_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'github_linked_at' => 'datetime',
        'resolved_at' => 'datetime',
        'status' => SupportTicketStatus::class,
        'area' => SupportTicketArea::class,
        'attachment' => 'array',
    ];

    public function cenHieDependency(): BelongsTo
    {
        return $this->belongsTo(CenHieDependency::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function indicatorType(): BelongsTo
    {
        return $this->belongsTo(IndicatorType::class);
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class)->orderBy('created_at');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SupportTicketEvent::class)->orderBy('created_at');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(SupportTicketRead::class);
    }

    public function githubLinkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'github_linked_by_user_id');
    }

    /**
     * @param  Builder<SupportTicket>  $query
     * @return Builder<SupportTicket>
     */
    public function scopeVisibleToUser($query, User $user)
    {
        if (in_array($user->role?->name, ['parametrizador'], true)) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole('admin')) {
            return $query;
        }

        $ssId = $user->cen_hie_dependency_id;
        if ($user->hasRole('referente')) {
            return $query->where('cen_hie_dependency_id', $ssId)
                ->where(function ($q) use ($user): void {
                    $q->where('created_by_user_id', $user->id)
                        ->orWhere('assigned_to_user_id', $user->id);
                });
        }

        if ($user->hasRole('visualizador')) {
            return $query->where('cen_hie_dependency_id', $ssId)
                ->where('created_by_user_id', $user->id);
        }

        return $query->where('cen_hie_dependency_id', $ssId);
    }
}
