@php
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator */
    $queryExcept = $queryExcept ?? [];
    $pageName = $pageName ?? 'page';
@endphp

@if ($paginator->hasPages())
    <div class="cen-read-section__foot clearfix">
        <div class="pull-right">
            {{ $paginator->appends(array_merge(request()->except($pageName), $queryExcept))->links('pagination::bootstrap-4') }}
        </div>
    </div>
@endif
