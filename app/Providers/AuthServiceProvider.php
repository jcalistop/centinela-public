<?php

namespace App\Providers;

use App\Models\AdpAgreement;
use App\Models\AdpAgreementPeriod;
use App\Models\AdpIndicator;
use App\Models\AdpIndicatorCatalogItem;
use App\Models\AdpIndicatorFormula;
use App\Models\AdpIndicatorTarget;
use App\Models\AdpMidtermEvaluation;
use App\Models\AdpMonthlyData;
use App\Models\AdpObjective;
use App\Models\AdpPosition;
use App\Models\AdpPositionAssignment;
use App\Models\CenCenter;
use App\Models\Document;
use App\Models\IndicatorActivity;
use App\Models\IndicatorAmbito;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\SupportTicket;
use App\Models\SupportTicketReceiver;
use App\Models\User;
use App\Policies\AdpAgreementPeriodPolicy;
use App\Policies\AdpAgreementPolicy;
use App\Policies\AdpIndicatorCatalogItemPolicy;
use App\Policies\AdpIndicatorFormulaPolicy;
use App\Policies\AdpIndicatorPolicy;
use App\Policies\AdpIndicatorTargetPolicy;
use App\Policies\AdpMidtermEvaluationPolicy;
use App\Policies\AdpMonthlyDataPolicy;
use App\Policies\AdpObjectivePolicy;
use App\Policies\AdpPositionAssignmentPolicy;
use App\Policies\AdpPositionPolicy;
use App\Policies\CenCenterPolicy;
use App\Policies\CurrentCutoffPolicy;
use App\Policies\CustomUserPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\IndicatorActivityPolicy;
use App\Policies\IndicatorAmbitoPolicy;
use App\Policies\IndicatorTypePolicy;
use App\Policies\IndicatorTypeSettingPolicy;
use App\Policies\SupportTicketPolicy;
use App\Policies\SupportTicketReceiverPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => CustomUserPolicy::class,
        AdpPosition::class => AdpPositionPolicy::class,
        AdpPositionAssignment::class => AdpPositionAssignmentPolicy::class,
        AdpAgreement::class => AdpAgreementPolicy::class,
        AdpAgreementPeriod::class => AdpAgreementPeriodPolicy::class,
        AdpObjective::class => AdpObjectivePolicy::class,
        AdpIndicator::class => AdpIndicatorPolicy::class,
        AdpIndicatorCatalogItem::class => AdpIndicatorCatalogItemPolicy::class,
        AdpIndicatorFormula::class => AdpIndicatorFormulaPolicy::class,
        AdpIndicatorTarget::class => AdpIndicatorTargetPolicy::class,
        AdpMonthlyData::class => AdpMonthlyDataPolicy::class,
        AdpMidtermEvaluation::class => AdpMidtermEvaluationPolicy::class,
        SupportTicket::class => SupportTicketPolicy::class,
        SupportTicketReceiver::class => SupportTicketReceiverPolicy::class,
        Document::class => DocumentPolicy::class,
        IndicatorAmbito::class => IndicatorAmbitoPolicy::class,
        IndicatorActivity::class => IndicatorActivityPolicy::class,
        IndicatorType::class => IndicatorTypePolicy::class,
        IndicatorTypeSetting::class => IndicatorTypeSettingPolicy::class,
        CenCenter::class => CenCenterPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
        Gate::define('current-add-edit', [CurrentCutoffPolicy::class, 'currentAddEdit']);
        Gate::define('current-download', [CurrentCutoffPolicy::class, 'currentDownload']);

        Gate::define('support-ticket-promote-github', function (User $user): bool {
            return app(SupportTicketPolicy::class)->createGithubIssue($user);
        });
    }
}
