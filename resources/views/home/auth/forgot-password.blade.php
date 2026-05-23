@extends('home.auth.layout.app')
@section('title', 'Login')
@section('content')
    <section class="section">
        <!-- Forget Password Start -->
        <div class="container-xxl py-5 pb-lg-5 pb-0 px-0 wow fadeInUp" data-wow-delay="0.1s">
            <div class="row g-0">
                <div class="col-xl-6 col-sm-8 col-11 mx-auto bg-primary d-flex align-items-center">
                    <div class="w-100 p-xl-5 p-4 wow fadeInUp light-box-shadow" data-wow-delay="0.2s">
                        <h5 class="section-title ff-secondary text-start text-primary fw-normal">Forget Password</h5>
                        <h1 class="text-dark mb-4">Retrieve Your Password</h1>
                        <form method="POST" action="{{ url('user-reset-password-link') }}" id="bot-protected-form">
                            @csrf
                            <!-- Bot Protection Fields -->
                            <input type="hidden" id="js_enabled_field" name="js_enabled" value="" class="d-none">
                            <input type="text" name="website" class="honeypot-field d-none" autocomplete="off">
                            <input type="hidden" id="form_start_time" name="form_start_time" value="">
                            
                            @if ($errors->has('form_bot'))
                                <div class="alert alert-danger">{{ $errors->first('form_bot') }}</div>
                            @endif
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="form-floating">
                                        <input type="email" name="email" class="form-control" placeholder="Your Email">
                                        <label for="email">Your Email</label>
                                    </div>
                                    @error('email')
                                        <span class="text-danger">{{ $errors->first('email') }}</span>
                                    @enderror
                                </div>
                                <div class="col-md-12 mt-3">
                                    <div class="form-group text-center">
                                        <div class="g-recaptcha" data-sitekey="{{ env('GOOGLE_RECAPTCHA_KEY') }}" data-callback="enableCaptchaSubmit"></div>
                                        @if ($errors->has('g-recaptcha-response'))
                                            <span class="text-danger">{{ $errors->first('g-recaptcha-response') }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-primary w-100 py-3" type="submit">Submit</button>
                                    <small class="mt-2 d-block text-danger">(Note: Please check your Spam/Junk folder in case the email is not received in the inbox. If it's not there as well, please contact us at contact@sugarpappi.com)</small>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!-- Forget Password End -->
    </section>
@endsection
@section('js')
    @if (\Illuminate\Support\Facades\Session::has('message'))
        <script>
            toastr.success('{{ \Illuminate\Support\Facades\Session::get('message') }}');
        </script>
    @endif
    <script>
        let formLoadTime = Date.now();
        $(function () {
            // 1. Record form load time
            $('#form_start_time').val(formLoadTime);

            // 2. Set JS-only field
            $('#js_enabled_field').val('true');

            // 3. On form submit — run checks
            $('#bot-protected-form').on('submit', function (e) {
                const currentTime = Date.now();
                const timeDiff = currentTime - formLoadTime;
                const jsEnabled = $('#js_enabled_field').val();
                const honeypotValue = $('input[name="website"]').val();

                if (timeDiff < 2000) {
                    e.preventDefault();
                    alert("Bot activity detected: Submitted too quickly.");
                    return false;
                }

                if (jsEnabled !== 'true') {
                    e.preventDefault();
                    alert("Bot activity detected: JavaScript check failed.");
                    return false;
                }

                if (honeypotValue.trim() !== "") {
                    e.preventDefault();
                    alert("Bot activity detected: Hidden field should be empty.");
                    return false;
                }
            });
        });
    </script>
@endsection
