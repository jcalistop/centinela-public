<x-centinela.compass.section :title="$title" :icon="$icon ?? null">
    <div @class(['cen-read-grid', 'cen-read-grid--2col' => !empty($twoCol)])>
        @foreach ($items ?? [] as $item)
            @include('voyager::establecimientos.partials.read-detail-item', $item)
        @endforeach
    </div>
</x-centinela.compass.section>
