<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Sugar Pappi</title>
      <!-- Developed By Ranglerz -->
      <link rel="stylesheet" href="https://www.ranglerz.com/cost-to-make-a-web-ios-or-android-app-and-how-long-does-it-take.php">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta content="" name="keywords">
    <meta content="" name="description">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Favicon -->
    <link href="{{ asset('public/img/logo.png') }}" rel="icon">
    <!-- Google Web Fonts -->
    <link rel="preconnect" href="{{ asset('https://fonts.googleapis.com') }}">
    <link rel="preconnect" href="{{ asset('https://fonts.gstatic.com') }}" crossorigin>
    <link
        href="{{ asset('https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600&family=Nunito:wght@600;700;800&family=Pacifico&display=swap') }}"
        rel="stylesheet">

    <!-- Icon Font Stylesheet -->
    <link href="{{ asset('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css') }}"
        rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('https://cdnjs.cloudflare.com/ajax/libs/remixicon/3.5.0/remixicon.css') }}"
        integrity="sha512-HXXR0l2yMwHDrDyxJbrMD9eLvPe3z3qL3PPeozNTsiHJEENxx8DH2CxmV05iwG0dwoz5n4gQZQyYLUNt1Wdgfg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="{{ asset('https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css') }}"
        rel="stylesheet">

    <!-- Libraries Stylesheet -->
    <link href="{{ asset('public/lib/animate/animate.min.css') }}" rel="stylesheet">
    <link href="{{ asset('public/lib/owlcarousel/assets/owl.carousel.min.css') }}" rel="stylesheet">
    <link href="{{ asset('public/lib/tempusdominus/css/tempusdominus-bootstrap-4.min.css') }}" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/1.6.14/css/lightgallery.css" />

    <!-- Customized Bootstrap Stylesheet -->
    <link href="{{ asset('public/css/bootstrap.min.css') }}" rel="stylesheet">
    <!-- Template Stylesheet -->
    <link href="{{ asset('public/css/sidebar.css') }}" rel="stylesheet">
    <link href="{{ asset('public/css/common.css') }}" rel="stylesheet">
    <link href="{{ asset('public/css/style.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('public/admin/assets/toastr/css/toastr.css') }}">
    
    <!-- Google reCAPTCHA Script -->
    <script src="https://www.google.com/recaptcha/api.js?render=explicit" async defer></script>
    <script>
        function enableCaptchaSubmit() {
            // This function will be called when reCAPTCHA is successfully completed
            console.log('reCAPTCHA completed');
        }
    </script>
</head>

<body>
    <div id="app" class="bg-white">
        <div class="main-wrapper main-wrapper-1">
            <div class="container-xxl bg-white p-0">
                @include('home.common.header')
                @include('home.common.side_menu')
                <div class="main-content">
                    @yield('content')
                </div>
                @include('home.common.footer')
            </div>
        </div>
    </div>

    <!-- CSS Libraries -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    
    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment-timezone/0.5.34/moment-timezone-with-data.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tempusdominus-bootstrap-4/5.39.0/js/tempusdominus-bootstrap-4.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/1.6.14/js/lightgallery-all.min.js"></script>
    <script src='https://www.google.com/recaptcha/api.js'></script>
    {{-- <script src="{{ asset('public/lib/wow/wow.min.js') }}"></script> --}}
    <script src="{{ asset('public/lib/easing/easing.min.js') }}"></script>
    <script src="{{ asset('public/lib/waypoints/waypoints.min.js') }}"></script>
    <script src="{{ asset('public/lib/counterup/counterup.min.js') }}"></script>
    <script src="{{ asset('public/lib/owlcarousel/owl.carousel.min.js') }}"></script>



    <!-- Template Javascript -->
    <script src="{{ asset('public/js/main.js') }}"></script>
    @yield('css')
    @yield('js')
</body>
<script>
    /*toastr popup function*/
    function toastrPopUp() {
        toastr.options = {
            "closeButton": true,
            "newestOnTop": false,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "preventDuplicates": false,
            "onclick": null,
            "showDuration": "3000",
            "hideDuration": "1000",
            "timeOut": "5000",
            "extendedTimeOut": "1000",
            "showEasing": "swing",
            "hideEasing": "linear",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut"
            // toastr.success('Success messages');
        }
    }

    /*toastr popup function*/
    toastrPopUp();

</script>


</html>
