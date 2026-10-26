<div>
    @if (!is_null($chartType) && isset($chart))
        <div style="margin-top: 50; margin-bottom: 50; margin-left: 20%; margin-right: 20%; border:#EAEAEA solid 1px;">
            {!! $chart->container() !!}
        </div>
    @endif
</div>
@push('javascript')
    @if (!is_null($chartType) && isset($chart))
        <script src="{{ $chart->cdn() }}"></script>
        {{ $chart->script() }}
    @endif
@endpush
