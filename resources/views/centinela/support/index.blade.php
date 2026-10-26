@extends('voyager::master')

@section('css')
    @include('centinela.support.partials.compass-shell')
@stop

@section('page_title', 'Soporte — Tickets')

@section('page_header')
    <h1 class="page-title">
        <i class="voyager-help"></i> Soporte Centinela
    </h1>
@stop

@section('content')
    <div class="page-content compass container-fluid support-compass cen-compass-ui support-module">
        @include('voyager::alerts')

        @if (session('flash_support_success'))
            <div class="alert alert-success">{{ session('flash_support_success') }}</div>
        @endif

        @if (session('flash_support_notice'))
            <div class="alert alert-info">{{ session('flash_support_notice') }}</div>
        @endif

        <p class="support-page-actions">
            <a class="btn btn-sm btn-primary" href="{{ route('centinela.support.create') }}">
                <i class="voyager-plus" aria-hidden="true"></i> Crear ticket
            </a>
            @if (auth()->user()->hasRole('admin'))
                <a class="btn btn-sm btn-success" href="{{ route('centinela.support.receivers.index') }}">
                    <i class="voyager-people" aria-hidden="true"></i> Receptores
                </a>
            @endif
        </p>

        {{--
            TODO Soporte bandeja (admin): evaluar si mas adelante hace falta un filtro visible por Servicio de Salud.
            Backend aun acepta `?ss=` solo para admins; UI removida a pedido UX.
        --}}
        {{-- Toolbar: estado se elige solo con las burbujas; aqui solo busqueda textual. --}}
        <div class="support-toolbar">
            <form method="get" class="m-b-0 support-toolbar-form" action="{{ route('centinela.support.index') }}"
                id="filter_form">
                @if (filled($statusFilter ?? ''))
                    <input type="hidden" name="status" value="{{ $statusFilter }}">
                @endif
                <input type="search" id="support_search" name="q" class="form-control support-toolbar-search"
                    placeholder="Buscar por asunto o ID…" value="{{ request('q') }}" autocomplete="off">

                <button type="submit" class="btn btn-sm btn-success">
                    <i class="voyager-search" aria-hidden="true"></i> Buscar
                </button>
            </form>
        </div>

        {{-- Pills: totales globales visibles para el usuario (sin filtro de estado / busqueda / SS). SDD-052 --}}
        <div class="support-count-pills">
            @foreach (\App\Enums\SupportTicketStatus::cases() as $st)
                @php
                    $val = $st->value;
                    $cnt = $ticketCountsByStatus[$val] ?? 0;
                @endphp
                <a href="{{ route('centinela.support.index', array_filter(['status' => $val, 'q' => request('q')])) }}"
                    class="support-count-pill support-count-pill--{{ $val }}" @selected(($statusFilter ?? '') === $val)>
                    <i class="voyager-tag" aria-hidden="true"></i> {{ $st->label() }} ({{ $cnt }})
                </a>
            @endforeach
            <a href="{{ route('centinela.support.index', array_filter(['q' => request('q')])) }}"
                class="support-count-pill support-count-pill--all" @selected(blank($statusFilter ?? null))>
                <i class="voyager-list" aria-hidden="true"></i> Todos ({{ $ticketsVisibleTotal }})
            </a>
        </div>

        <x-centinela.support.compass-block title="Bandeja" icon="voyager-list" class="m-t-10">
            @if ($tickets->isEmpty())
                <div class="support-empty-state">
                    <span class="support-empty-icon voyager-inbox"></span>
                    @if (request()->anyFilled(['status', 'ss', 'q']))
                        <p>No hay tickets que coincidan con los filtros aplicados.</p>
                        <a href="{{ route('centinela.support.index') }}" class="btn btn-sm btn-success">
                            <i class="voyager-refresh" aria-hidden="true"></i> Limpiar filtros
                        </a>
                    @else
                        <p>Aun no hay tickets de soporte.</p>
                        <a href="{{ route('centinela.support.create') }}" class="btn btn-sm btn-primary">
                            <i class="voyager-plus" aria-hidden="true"></i> Crear primer ticket
                        </a>
                    @endif
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered table-striped cen-compass-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Asunto</th>
                                <th>Estado</th>
                                <th>Area</th>
                                <th>SS</th>
                                <th>Asignado a</th>
                                <th>Ultima actividad</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tickets as $ticket)
                                @php
                                    $unread = $unreadByTicketId[$ticket->id] ?? false;
                                    $statusVal = $ticket->status->value;
                                    $lastAct = $lastActivityAtByTicketId[$ticket->id] ?? null;
                                    $lastActCarbon = $lastAct ?? $ticket->created_at;
                                @endphp
                                <tr class="{{ $unread ? 'support-row-unread' : '' }} support-row--{{ $statusVal }}">
                                    <td data-label="ID">{{ $ticket->id }}</td>
                                    <td data-label="Asunto">
                                        <a
                                            href="{{ route('centinela.support.show', $ticket) }}">{{ $ticket->subject }}</a>
                                        @if ($unread)
                                            <span class="support-badge-unread">Nuevo</span>
                                        @endif
                                    </td>
                                    <td data-label="Estado">
                                        <span
                                            class="label label-default support-status-badge support-status-badge--{{ $statusVal }}">
                                            {{ $ticket->status->label() }}
                                        </span>
                                    </td>
                                    <td data-label="Area">
                                        <span
                                            class="support-area-badge support-area-badge--{{ $ticket->area->value ?? $ticket->area }}">
                                            {{ \App\Support\SupportTicket\SupportTicketCatalog::labelForArea($ticket->area) }}
                                        </span>
                                    </td>
                                    <td data-label="SS">
                                        {{ $ticket->cenHieDependency?->nombre ?? $ticket->cen_hie_dependency_id }}</td>
                                    <td data-label="Asignado a">
                                        @if ($ticket->assignedTo)
                                            {{ $ticket->assignedTo->name }}
                                            <span class="text-muted">({{ $ticket->assignedTo->email ?? '—' }})</span>
                                        @else
                                            <span class="text-muted">— Sin asignar —</span>
                                        @endif
                                    </td>
                                    <td data-label="Ultima actividad">
                                        <span class="support-relative-time"
                                            title="{{ $lastActCarbon?->format('d-m-Y H:i') }}">
                                            {{ $lastActCarbon?->diffForHumans() ?? '—' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $tickets->links() }}
            @endif
        </x-centinela.support.compass-block>
    </div>

@stop
