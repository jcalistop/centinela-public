@extends('voyager::master')

@section('page_title', $doc->title)

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-bordered">
                    <div class="panel-heading">
                        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
                            <h3 class="panel-title" style="margin:0;">{{ $doc->title }}</h3>
                            <a class="btn btn-sm btn-default" href="{{ route('user-manual.index') }}">Ver índice</a>
                        </div>
                    </div>
                    <div class="panel-body user-manual-content">
                        {!! $doc->html !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

