@extends('voyager::auth.master')

@section('content')
    <div class="login-container">

        <p>{{ __('Reset Password') }}</p>

        <div class="card-body">
            @if (session('status'))
                <div class="alert alert-success" role="alert">
                    <ul class="list-unstyled">
                        <li><strong>{{ session('status') }}</strong></li>
                    </ul>
                </div>
                <a href="{{ route('voyager.login') }}" class="btn btn-primary">
                    Volver
                </a>
            @else
                <form method="POST" action="{{ route('password.email') }}">
                    {{ csrf_field() }}
                    <div class="form-group form-group-default" id="emailGroup">
                        <label>{{ __('Email Address') }}</label>
                        <div class="controls">
                            <input type="email" name="email" id="email" value="{{ old('email') }}"
                                placeholder="{{ __('voyager::generic.email') }}"
                                class="form-control @error('email') is-invalid @enderror" autocomplete="email" autofocus>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        {{ __('Send Password Reset Link') }}
                    </button>
                </form>
                @error('email')
                <div class="alert alert-red">
                    <ul class="list-unstyled">
                        <li><strong>{{ $message }}</strong></li>
                    </ul>
                </div>
                @enderror
            @endif
        </div>
    </div>
@endsection

@section('post_js')
    <script>
        var email = document.querySelector('[name="email"]');
        email.focus();
        document.getElementById('emailGroup').classList.add("focused");

        // Focus events for email and password fields
        email.addEventListener('focusin', function(e) {
            document.getElementById('emailGroup').classList.add("focused");
        });
        email.addEventListener('focusout', function(e) {
            document.getElementById('emailGroup').classList.remove("focused");
        });
    </script>
@endsection
