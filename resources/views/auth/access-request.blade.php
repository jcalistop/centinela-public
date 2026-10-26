@extends('voyager::auth.master')

@section('pre_css')
    <style>
        body.login .login-sidebar {
            overflow-y: auto;
            max-height: 100vh;
            -webkit-overflow-scrolling: touch;
        }

        body.login .login-container.access-request-form {
            padding-bottom: 2rem;
        }

        body.login .access-request-form .form-group-default {
            margin-bottom: 0.75rem;
        }

        body.login .access-request-form #cen_hie_dependency_id optgroup,
        body.login .access-request-form #cen_hie_dependency_id option {
            text-transform: none;
            font-variant: normal;
        }
    </style>
@endsection

@section('content')
    <div class="login-container access-request-form">
        <p><strong>Solicitar acceso a Centinela</strong></p>
        <p class="text-muted" style="font-size: 13px;">
            Complete el formulario si aún no tiene usuario en la plataforma.
            Si ya tiene cuenta y olvidó su contraseña,
            <a href="{{ route('password.request') }}">use recuperación de contraseña</a>.
        </p>

        @if (session('access_request_success'))
            <div class="alert alert-success" role="alert">
                {{ session('access_request_success') }}
            </div>
        @endif

        @if (session('access_request_error'))
            <div class="alert alert-danger" role="alert">
                {{ session('access_request_error') }}
            </div>
        @endif

        @if (!$errors->isEmpty())
            <div class="alert alert-danger">
                <ul class="list-unstyled" style="margin-bottom: 0;">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('centinela.access-request.store') }}">
            @csrf

            <div class="form-group form-group-default">
                <label for="nombre">Nombre completo</label>
                <div class="controls">
                    <input type="text" name="nombre" id="nombre" class="form-control" required maxlength="200"
                        value="{{ old('nombre') }}" autocomplete="name">
                </div>
            </div>

            <div class="form-group form-group-default">
                <label for="cargo">Cargo (opcional)</label>
                <div class="controls">
                    <input type="text" name="cargo" id="cargo" class="form-control" maxlength="200"
                        value="{{ old('cargo') }}">
                </div>
            </div>

            <div class="form-group form-group-default">
                <label for="cen_hie_dependency_id">Servicio de salud</label>
                <div class="controls">
                    <select name="cen_hie_dependency_id" id="cen_hie_dependency_id" class="form-control" required>
                        <option value="">Seleccione…</option>
                        @foreach ($serviciosHabilitados as $ss)
                            <option value="{{ $ss->id }}" @selected((int) old('cen_hie_dependency_id') === (int) $ss->id)>
                                {{ $ss->codigo }} — {{ $ss->nombre }}
                            </option>
                        @endforeach
                        <option value="0" @selected((int) old('cen_hie_dependency_id') === 0)>
                            Otro servicio de salud (no listado)
                        </option>
                    </select>
                </div>
            </div>

            <div class="form-group form-group-default" id="servicioSaludOtroGroup"
                @if ((int) old('cen_hie_dependency_id') !== 0) hidden @endif>
                <label for="servicio_salud_nombre">Nombre del servicio de salud</label>
                <div class="controls">
                    <input type="text" name="servicio_salud_nombre" id="servicio_salud_nombre" class="form-control"
                        maxlength="200" value="{{ old('servicio_salud_nombre') }}"
                        @if ((int) old('cen_hie_dependency_id') === 0) required @endif>
                </div>
                <p class="text-muted" style="font-size: 12px; margin-top: 0.35rem; margin-bottom: 0;">
                    Su solicitud será revisada por el equipo central de la plataforma.
                </p>
            </div>

            <div class="form-group form-group-default">
                <label for="email">Correo electrónico</label>
                <div class="controls">
                    <input type="email" name="email" id="email" class="form-control" required maxlength="255"
                        value="{{ old('email') }}" autocomplete="email">
                </div>
            </div>

            <div class="form-group form-group-default">
                <label for="telefono">Teléfono (opcional)</label>
                <div class="controls">
                    <input type="text" name="telefono" id="telefono" class="form-control" maxlength="30"
                        value="{{ old('telefono') }}" autocomplete="tel">
                </div>
            </div>

            <div class="form-group form-group-default">
                <label for="mensaje">Mensaje (opcional)</label>
                <div class="controls">
                    <textarea name="mensaje" id="mensaje" class="form-control" rows="4" maxlength="5000">{{ old('mensaje') }}</textarea>
                </div>
            </div>

            @if (config('centinela.login_legal_documents.enabled'))
                <p class="text-muted" style="font-size: 12px;">
                    Al enviar, declara que los datos son verídicos. Consulte la
                    <a href="{{ route('centinela.legal.terminos') }}" target="_blank" rel="noopener noreferrer">términos de
                        uso</a>
                    y la
                    <a href="{{ route('centinela.legal.privacidad') }}" target="_blank" rel="noopener noreferrer">política de
                        privacidad</a>.
                </p>
            @endif

            <button type="submit" class="btn btn-block login-button">
                <span class="signin">Enviar solicitud</span>
            </button>
        </form>

        <p class="text-center" style="margin-top: 1rem;">
            <a href="{{ route('voyager.login') }}">Volver al inicio de sesión</a>
        </p>

        <div style="clear:both"></div>
    </div>
@endsection

@section('post_js')
    <script>
        (function() {
            var select = document.getElementById('cen_hie_dependency_id');
            var otroGroup = document.getElementById('servicioSaludOtroGroup');
            var otroInput = document.getElementById('servicio_salud_nombre');

            function syncOtroField() {
                var isOtro = select.value === '0';
                otroGroup.hidden = !isOtro;
                otroInput.required = isOtro;
                if (!isOtro) {
                    otroInput.value = '';
                }
            }

            select.addEventListener('change', syncOtroField);
            syncOtroField();
        })();
    </script>
@endsection
