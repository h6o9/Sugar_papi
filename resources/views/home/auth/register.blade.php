@extends('home.auth.layout.app')
@section('title', 'Login')
@section('content')
    <section class="section">
        <div class="container-xxl py-5 pb-lg-5 pb-0 px-0 wow fadeInUp" data-wow-delay="0.1s">
            <div class="row g-0">
                <div class="col-xl-6 col-sm-8 col-11 mx-auto bg-primary d-flex align-items-center">
                    <div class="p-xl-5 p-4 wow fadeInUp light-box-shadow" data-wow-delay="0.2s">
                        <h5 class="section-title ff-secondary text-start text-dark fw-normal">Sign Up</h5>
                        <h1 class="text-dark mb-4">Join the Sugar Pappi family — where flavor meets fun!</h1>

                        <form method="POST" action="{{ route('registerUser') }}" id="bot-protected-form">
                            @csrf 
                            <!-- Bot Protection Fields -->
                            <input type="hidden" id="js_enabled_field" name="js_enabled" value="" class="d-none">
                            <input type="text" name="website" class="honeypot-field d-none" autocomplete="off">
                            <input type="hidden" id="form_start_time" name="form_start_time" value="">
                            
                            @if ($errors->has('form_bot'))
                                <div class="alert alert-danger">{{ $errors->first('form_bot') }}</div>
                            @endif
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="name" name="name"
                                            placeholder="Your Name">
                                        <label for="name">Full Name</label>
                                    </div>
                                    @error('name')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="email" class="form-control" id="email" name="email"
                                            placeholder="Your Email">
                                        <label for="email">Email Address</label>
                                    </div>
                                    @error('email')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
								<div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="phone" name="phone"
                                            placeholder="Your Phone">
                                        <label for="phone">Phone Number</label>
                                    </div>
                                    @error('phone')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
								<div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="address" name="address"
                                            placeholder="Your Address">
                                        <label for="address">Address</label>
                                    </div>
                                    @error('address')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
								<div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="postcode" name="postcode"
                                            placeholder="Your PostCode">
                                        <label for="postcode">Postcode</label>
                                    </div>
                                    @error('postcode')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating position-relative d-flex align-items-center">
                                        <input type="password" class="form-control" id="inputPassword" name="password"
                                            placeholder="Type Your Password" style="padding-right: 2.5rem">
                                        <label for="inputPassword">Password</label>
                                        <span toggle="#inputPassword" class="fa fa-eye toggle-password"></span>
                                    </div>
                                    @error('password')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating position-relative d-flex align-items-center">
                                        <input type="password" class="form-control" id="inputConfirmPassword"
                                            name="password_confirmation" placeholder="Confirm Your Password"
                                            style="padding-right: 2.5rem">
                                        <label for="inputConfirmPassword">Confirm Password</label>
                                        <span toggle="#inputConfirmPassword" class="fa fa-eye toggle-password"></span>
                                    </div>
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
                                    <button class="btn btn-primary w-100 py-3" type="submit">Sign Up</button>
                                    <small class="mt-2 d-block text-danger">(Note: Please check your Spam/Junk folder in case the email is not received in the inbox. If it's not there as well, please contact us at contact@sugarpappi.com)</small>
                                    <h5 class="text-dark text-center mt-4 mb-0">Already have an account? <a
                                            href="{{ route('login') }}" class="text-dark">Login</a></h5>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
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
