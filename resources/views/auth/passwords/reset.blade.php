@extends('voyager::auth.master')

@section('content')
    <div class="login-container">

        <p>{{ __('Reset Password') }}</p>

            <form action="{{ route('password.update') }}" method="POST">
                {{ csrf_field() }}
                <div class="form-group form-group-default" id="emailGroup">
                    <input type="hidden" name="token" value="{{ $token }}">
                    <label for="email" class="col-md-4 col-form-label text-md-end">{{ __('Email Address') }}</label>
                    <div class="controls">
                        <input id="email" type="email" class="form-control @error('email') is-invalid @enderror"
                            name="email" value="{{ $email ?? old('email') }}" required autocomplete="email" readonly>
                        @error('email')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                </div>
                
                <div class="form-group form-group-default" id="passwordGroup">
                    <label for="password">{{ __('Password') }}</label>
                    <div class="controls">
                        <input id="password" type="password" class="form-control @error('password') is-invalid @enderror"
                            name="password" required autocomplete="new-password">
                        @error('password')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                </div>

                <div class="form-group form-group-default" id="passwordConfirmGroup">
                    <label for="password-confirm">{{ __('Confirm Password') }}</label>
                    <div class="controls">
                        <input id="password-confirm" type="password" class="form-control" name="password_confirmation"
                            required autocomplete="new-password">
                    </div>
                </div>

                        <button type="submit" class="btn btn-primary">
                            {{ __('Reset Password') }}
                        </button>
            </form>
        </div>
@endsection

@section('post_js')
    <script>
        var email = document.querySelector('[name="email"]');
        var password = document.querySelector('[name="password"]');
        var passwordConfirm = document.querySelector('[name="password_confirmation"]');
        document.getElementById('passwordGroup').classList.add("focused");

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

        passwordConfirm.addEventListener('focusin', function(e) {
            document.getElementById('passwordConfirmGroup').classList.add("focused");
        });
        passwordConfirm.addEventListener('focusout', function(e) {
            document.getElementById('passwordConfirmGroup').classList.remove("focused");
        });
    </script>
@endsection
