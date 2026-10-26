@extends('voyager::auth.master')

@section('content')
    <div class="login-container">

        @if (request()->boolean('session_expired'))
            <div class="alert alert-info" role="alert" style="margin-bottom: 1rem;">
                {{ __('auth.session_expired_login_notice') }}
            </div>
        @endif

        <p>{{ __('voyager::login.signin_below') }}</p>

        <form action="{{ route('voyager.login') }}" method="POST">
            {{ csrf_field() }}
            <div class="form-group form-group-default" id="emailGroup">
                <label>{{ __('voyager::generic.email') }}</label>
                <div class="controls">
                    <input type="text" name="email" id="email" value="{{ old('email') }}"
                        placeholder="{{ __('voyager::generic.email') }}" class="form-control" required>
                </div>
            </div>

            <div class="form-group form-group-default" id="passwordGroup">
                <label>{{ __('voyager::generic.password') }}</label>
                <div class="controls">
                    <input type="password" name="password" placeholder="{{ __('voyager::generic.password') }}"
                        class="form-control" required>
                </div>
            </div>

            <div class="form-group" id="rememberMeGroup">
                <div class="controls">
                    <input type="checkbox" name="remember" id="remember" value="1"><label for="remember"
                        class="remember-me-text">{{ __('voyager::generic.remember_me') }}</label>
                </div>
            </div>
            @if (Route::has('password.request'))
                <a class="btn btn-link" href="{{ route('password.request') }}">
                    {{ __('Forgot Your Password?') }}
                </a>
            @endif
            <button type="submit" class="btn btn-block login-button">
                <span class="signingin hidden"><span class="voyager-refresh"></span>
                    {{ __('voyager::login.loggingin') }}...</span>
                <span class="signin">{{ __('voyager::generic.login') }}</span>
            </button>

            @if (\App\Support\Configuration\PublicDashboardGate::enabled())
                <p class="text-center login-public-dashboard-link" style="margin-top: 1.25rem; margin-bottom: 0;">
                    <a class="btn btn-link" href="{{ route('centinela.publico.landing') }}" style="font-size: 13px;">
                        Ver dashboard público
                    </a>
                </p>
            @endif

            <p class="text-center login-access-request-link"
                style="margin-top: 0.75rem; margin-bottom: 0; padding-top: 0.5rem;">
                <a class="btn btn-link" href="{{ route('centinela.access-request.create') }}" style="font-size: 13px;">
                    ¿No tienes usuario habilitado?
                </a>
            </p>

        </form>

        @if (config('centinela.login_legal_documents.enabled'))
            <p class="text-center text-muted" style="margin-top: 1rem; font-size: 12px;">
                <a href="{{ route('centinela.legal.terminos') }}" target="_blank" rel="noopener noreferrer">Términos de
                    uso</a>
                ·
                <a href="{{ route('centinela.legal.privacidad') }}" target="_blank" rel="noopener noreferrer">Política de
                    privacidad</a>
            </p>
        @endif

        <div style="clear:both"></div>

        @if (!$errors->isEmpty())
            <div class="alert alert-red">
                <ul class="list-unstyled">
                    @foreach ($errors->all() as $err)
                        <li><strong>{{ $err }}<strong></li>
                    @endforeach
                </ul>
            </div>
        @endif

    </div>
@endsection

@section('post_js')
    <script>
        var btn = document.querySelector('button[type="submit"]');
        var form = document.forms[0];
        var email = document.querySelector('[name="email"]');
        var password = document.querySelector('[name="password"]');
        btn.addEventListener('click', function(ev) {
            if (form.checkValidity()) {
                btn.querySelector('.signingin').className = 'signingin';
                btn.querySelector('.signin').className = 'signin hidden';
            } else {
                ev.preventDefault();
            }
        });
        email.focus();
        document.getElementById('emailGroup').classList.add("focused");

        // Focus events for email and password fields
        email.addEventListener('focusin', function(e) {
            document.getElementById('emailGroup').classList.add("focused");
        });
        email.addEventListener('focusout', function(e) {
            document.getElementById('emailGroup').classList.remove("focused");
        });

        password.addEventListener('focusin', function(e) {
            document.getElementById('passwordGroup').classList.add("focused");
        });
        password.addEventListener('focusout', function(e) {
            document.getElementById('passwordGroup').classList.remove("focused");
        });
    </script>
@endsection
