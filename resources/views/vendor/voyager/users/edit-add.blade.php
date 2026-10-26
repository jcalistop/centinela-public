@extends('voyager::master')

@section('page_title', __('voyager::generic.' . (isset($dataTypeContent->id) ? 'edit' : 'add')) . ' ' .
    $dataType->getTranslatedAttribute('display_name_singular'))

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@stop

@section('page_header')
    <h1 class="page-title">
        <i class="{{ $dataType->icon }}"></i>
        {{ __('voyager::generic.' . (isset($dataTypeContent->id) ? 'edit' : 'add')) . ' ' . $dataType->getTranslatedAttribute('display_name_singular') }}
    </h1>
@stop

@section('content')
    <div class="page-content container-fluid">
        <form class="form-edit-add" role="form"
            action="@if (!is_null($dataTypeContent->getKey())) {{ route('voyager.' . $dataType->slug . '.update', $dataTypeContent->getKey()) }}@else{{ route('voyager.' . $dataType->slug . '.store') }} @endif"
            method="POST" enctype="multipart/form-data" autocomplete="off">
            <!-- PUT Method if we are editing -->
            @if (isset($dataTypeContent->id))
                {{ method_field('PUT') }}
            @endif
            {{ csrf_field() }}

            <div class="row">
                <div class="col-md-8">
                    <div class="panel panel-bordered">
                        {{-- <div class="panel"> --}}
                        @if (count($errors) > 0)
                            <div class="alert alert-danger">
                                <ul>
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="panel-body">
                            @php
                                $voyagerAuthUser = auth()->user();
                            @endphp
                            <div class="form-group">
                                <label for="name">{{ __('voyager::generic.name') }}</label>
                                <input type="text" class="form-control" id="name" name="name"
                                    placeholder="{{ __('voyager::generic.name') }}"
                                    value="{{ old('name', $dataTypeContent->name ?? '') }}">
                            </div>

                            <div class="form-group">
                                <label for="email">{{ __('voyager::generic.email') }}</label>
                                <input type="email" class="form-control" id="email" name="email"
                                    placeholder="{{ __('voyager::generic.email') }}"
                                    value="{{ old('email', $dataTypeContent->email ?? '') }}">
                            </div>

                            <div class="form-group">
                                <label for="password">{{ __('voyager::generic.password') }}</label>
                                @if (isset($dataTypeContent->password))
                                    <br>
                                    <small>{{ __('voyager::profile.password_hint') }}</small>
                                @endif
                                <input type="password" class="form-control" id="password" name="password" value=""
                                    autocomplete="new-password">
                            </div>

                            @can('editRoles', $dataTypeContent)
                                @if (!isset($dataTypeContent->id) || (int) auth()->id() !== (int) $dataTypeContent->id)
                                    <div class="form-group">
                                        <label for="send_credentials_email">Enviar credenciales por correo</label>
                                        <br>
                                        <input type="checkbox" name="send_credentials_email" id="send_credentials_email"
                                            class="toggleswitch" value="1" data-on="Sí" data-off="No"
                                            @checked(old('send_credentials_email'))>
                                        <small class="text-muted d-block" style="margin-top: 8px;">
                                            Al guardar, se enviará un correo con el usuario y la contraseña indicada.
                                            @if (isset($dataTypeContent->id))
                                                Debe indicar una contraseña nueva para enviar credenciales.
                                            @endif
                                        </small>
                                    </div>
                                @endif
                            @endcan

                            <div class="form-group">
                                <label for="unidad">Unidad de trabajo</label>
                                <input type="text" class="form-control" id="unidad" name="unidad"
                                    placeholder="Unidad de trabajo"
                                    value="{{ old('unidad', $dataTypeContent->unidad ?? '') }}">
                            </div>

                            <div class="form-group">
                                <label for="anexo">Anexo red Minsal</label>
                                <input type="number" class="form-control" id="anexo" name="anexo"
                                    placeholder="Anexo red Minsal"
                                    value="{{ old('anexo', $dataTypeContent->anexo ?? '') }}">
                            </div>

                            @php
                                $mostrarServicioSalud = !(
                                    $voyagerAuthUser instanceof \App\Models\User &&
                                    $voyagerAuthUser->hasRole('control-gestion')
                                );
                            @endphp
                            @if ($mostrarServicioSalud)
                                <div class="form-group">
                                    <label for="cen_hie_dependency">Servicio de Salud</label>
                                    @php
                                        $dataTypeRows =
                                            $dataType->{isset($dataTypeContent->id) ? 'editRows' : 'addRows'};

                                        $row = $dataTypeRows
                                            ->where('field', 'user_belongsto_cen_hie_dependency_relationship')
                                            ->first();
                                        $options = $row->details;
                                    @endphp
                                    @include('voyager::formfields.relationship')
                                </div>
                            @endif
                            @can('editRoles', $dataTypeContent)
                                {{-- <div class="form-group">
                                    <label for="additional_roles">{{ __('voyager::profile.roles_additional') }}</label>
                                    @php
                                    $row     = $dataTypeRows->where('field', 'user_belongstomany_role_relationship')->first();
                                    $options = $row->details;
                                    @endphp
                                    @include('voyager::formfields.relationship')
                                </div> --}}
                                <div class="form-group">
                                    <label for="default_role">Rol del usuario</label>
                                    @php
                                        $dataTypeRows =
                                            $dataType->{isset($dataTypeContent->id) ? 'editRows' : 'addRows'};

                                        $row = $dataTypeRows
                                            ->where('field', 'user_belongsto_role_relationship')
                                            ->first();
                                        $options = $row->details;
                                        $filterRolesForControlGestion =
                                            $voyagerAuthUser instanceof \App\Models\User &&
                                            $voyagerAuthUser->hasRole('control-gestion');
                                    @endphp
                                    @if ($filterRolesForControlGestion)
                                        <select class="form-control select2" name="{{ $options->column }}"
                                            @if ($row->required == 1) required @endif>
                                            @foreach (\App\Models\Role::query()->whereNotIn('name', \App\Support\Voyager\VoyagerUserActorAuthorization::PROTECTED_ROLE_NAMES)->orderBy('display_name')->get() as $roleOption)
                                                <option value="{{ $roleOption->{$options->key} }}"
                                                    @selected((string) old($options->column, $dataTypeContent->{$options->column} ?? '') === (string) $roleOption->{$options->key})>
                                                    {{ $roleOption->{$options->label} }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @else
                                        @include('voyager::formfields.relationship')
                                    @endif
                                </div>
                            @endcan
                            @php
                                if (isset($dataTypeContent->locale)) {
                                    $selected_locale = $dataTypeContent->locale;
                                } else {
                                    $selected_locale = config('app.locale', 'en');
                                }

                            @endphp
                            {{-- <div class="form-group">
                                <label for="locale">{{ __('voyager::generic.locale') }}</label>
                                <select class="form-control select2" id="locale" name="locale">
                                    @foreach (Voyager::getLocales() as $locale)
                                    <option value="{{ $locale }}"
                                    {{ ($locale == $selected_locale ? 'selected' : '') }}>{{ $locale }}</option>
                                    @endforeach
                                </select>
                            </div> --}}
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="panel panel panel-bordered panel-warning">
                        <div class="panel-body">
                            <div class="form-group">
                                @if (isset($dataTypeContent->avatar))
                                    <img src="{{ filter_var($dataTypeContent->avatar, FILTER_VALIDATE_URL) ? $dataTypeContent->avatar : Voyager::image($dataTypeContent->avatar) }}"
                                        style="width:200px; height:auto; clear:both; display:block; padding:2px; border:1px solid #ddd; margin-bottom:10px;" />
                                @endif
                                <input type="file" data-name="avatar" name="avatar">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary pull-right save">
                {{ __('voyager::generic.save') }}
            </button>
        </form>
        <div style="display:none">
            <input type="hidden" id="upload_url" value="{{ route('voyager.upload') }}">
            <input type="hidden" id="upload_type_slug" value="{{ $dataType->slug }}">
        </div>
    </div>
@stop

@section('javascript')
    <script>
        $('document').ready(function() {
            $('.toggleswitch').bootstrapToggle();
        });
    </script>
@stop
