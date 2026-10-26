<?php

namespace App\FormFields;

use TCG\Voyager\FormFields\AbstractHandler;

class ReadOnlyFormField extends AbstractHandler
{
    protected $codename = 'read_only';

    public function createContent($row, $dataType, $dataTypeContent, $options)
    {
        return view('formfields.read_only', [
            'row' => $row,
            'options' => $options,
            'dataType' => $dataType,
            'dataTypeContent' => $dataTypeContent,
        ]);
    }
}
