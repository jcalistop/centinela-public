<?php

namespace App\Http\Controllers\Voyager;

use App\Support\Voyager\CentinelaValidatesBreadUploads;
use TCG\Voyager\Http\Controllers\Controller as VoyagerController;

abstract class CentinelaVoyagerController extends VoyagerController
{
    use CentinelaValidatesBreadUploads;
}
