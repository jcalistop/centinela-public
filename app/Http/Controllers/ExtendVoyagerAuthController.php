<?php

namespace App\Http\Controllers;

use App\Support\CentinelaSession;
use Illuminate\Http\Request;
use TCG\Voyager\Http\Controllers\VoyagerAuthController as BaseVoyagerAuthController;

class ExtendVoyagerAuthController extends BaseVoyagerAuthController
{
    public function postLogin(Request $request)
    {
        CentinelaSession::bootstrap();

        return parent::postLogin($request);
    }
}
