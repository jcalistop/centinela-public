<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Models\User;
use App\Support\Configuration\AdminBackgroundImage;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class VoyagerMasterComposer
{
    public function compose(View $view): void
    {
        $view->with('adminBackgroundImageUrl', AdminBackgroundImage::cachedUrl());

        $user = Auth::user();
        if ($user instanceof User && $user->cen_hie_dependency_id && ! $user->relationLoaded('cenHieDependency')) {
            $user->load('cenHieDependency');
        }
    }
}
