<?php

namespace App\Models;

use App\Enums\Cen\CenCatalogGlosaRuleKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CenCatalogGlosaEquivalence extends Model
{
    protected $table = 'cen_catalog_glosa_equivalences';

    /** @var list<string> */
    protected $fillable = [
        'field_key',
        'source_glosa',
        'source_glosa_normalized',
        'target_value',
        'rule_kind',
        'active',
        'reviewed_by_user_id',
        'source_sync_run_issue_id',
    ];

    protected $casts = [
        'active' => 'boolean',
        'rule_kind' => CenCatalogGlosaRuleKind::class,
    ];

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function sourceSyncRunIssue(): BelongsTo
    {
        return $this->belongsTo(CenCenterSyncRunIssue::class, 'source_sync_run_issue_id');
    }
}
