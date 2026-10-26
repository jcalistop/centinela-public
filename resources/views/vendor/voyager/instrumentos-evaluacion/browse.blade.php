@extends('voyager::bread.browse')

@php
    // Forzar orden inicial del browse por `orden` asc.
    // Esto afecta principalmente a DataTables client-side; para server-side, el controller
    // ya respeta `order_by` / `sort_order` en querystring.
    $orderColumn = [];
    $ordenRowIndex = $dataType->browseRows->search(function ($row) {
        return $row->field === 'orden';
    });

    if ($ordenRowIndex !== false) {
        $dtIndex = (int) $ordenRowIndex + ($showCheckboxColumn ? 1 : 0);
        $orderColumn = [[$dtIndex, 'asc']];
    }
@endphp

