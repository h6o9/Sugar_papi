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
    {{-- <link rel="stylesheet" href="{{ asset('https://cdnjs.cloudflare.com/ajax/libs/remixicon/3.5.0/remixicon.css') }}"
        integrity="sha512-HXXR0l2yMwHDrDyxJbrMD9eLvPe3z3qL3PPeozNTsiHJEENxx8DH2CxmV05iwG0dwoz5n4gQZQyYLUNt1Wdgfg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" /> --}}
    <link href="{{ asset('https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css') }}"
        rel="stylesheet">
    
    <!-- Google reCAPTCHA Script -->
    <script src="https://www.google.com/recaptcha/api.js?render=explicit" async defer></script>
    <script>
        function enableCaptchaSubmit() {
            // This function will be called when reCAPTCHA is successfully completed
            console.log('reCAPTCHA completed');
        }
    </script>

    <!-- Libraries Stylesheet -->
    <link href="{{ asset('public/lib/animate/animate.min.css') }}" rel="stylesheet">
    <link href="{{ asset('public/lib/owlcarousel/assets/owl.carousel.min.css') }}" rel="stylesheet">
    <link href="{{ asset('public/lib/tempusdominus/css/tempusdominus-bootstrap-4.min.css') }}" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/1.6.14/css/lightgallery.css" />

    <!-- Customized Bootstrap Stylesheet -->
    <link href="{{ asset('public/css/bootstrap.min.css') }}" rel="stylesheet">
    {{-- side bar link --}}
    <link href="{{ asset('public/css/sidebar.css') }}" rel="stylesheet">
    <!-- Template Stylesheet -->
    <link href="{{ asset('public/css/common.css') }}" rel="stylesheet">
    <link href="{{ asset('public/css/style.css') }}" rel="stylesheet">
    <!-- Template toster -->
    <link rel="stylesheet" href="{{ asset('public/admin/assets/toastr/css/toastr.css') }}">
    @yield('style')
</head>

<body>

    <div class="loader"></div>

    <div id="app" class="bg-white">
        <div class="main-wrapper main-wrapper-1">
                @include('home.common.header')
                @include('home.common.side_menu')
                @yield('content')
                @include('home.common.footer')
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="{{ asset('https://code.jquery.com/jquery-3.4.1.min.js') }}"></script>
    <script src="{{ asset('https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('public/lib/wow/wow.min.js') }}"></script>
    <script src="{{ asset('public/lib/easing/easing.min.js') }}"></script>
    <script src="{{ asset('public/lib/waypoints/waypoints.min.js') }}"></script>
    <script src="{{ asset('public/lib/counterup/counterup.min.js') }}"></script>
    <script src="{{ asset('public/lib/owlcarousel/owl.carousel.min.js') }}"></script>
    <script src="{{ asset('public/lib/tempusdominus/js/moment.min.js') }}"></script>
    <script src="{{ asset('public/lib/tempusdominus/js/moment-timezone.min.js') }}"></script>
    <script src="{{ asset('public/lib/tempusdominus/js/tempusdominus-bootstrap-4.min.js') }}"></script>

    <!-- Template Javascript -->
    <script src="{{ asset('public/js/main.js') }}"></script>
    <!-- Sweet Alert -->
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('public/admin/assets/toastr/js/toastr.min.js') }}"></script>
    <script src="{{ asset('public/admin/assets/js/scripts.js') }}"></script>
    <script src='https://www.google.com/recaptcha/api.js'></script>
    {{-- @yield('css')
    @yield('js') --}}
</body>
@yield('script')
<script>
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        width: '27rem',
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    })
    @if (Session()->has('message'))
        var type = "{{ Session::get('alert') }}";
        switch (type) {
            case 'info':
                Toast.fire({
                    icon: 'info',
                    title: '{{ Session::get('message') }}'
                })
                break;
            case 'success':
                Toast.fire({
                    icon: 'success',
                    title: '{{ Session::get('message') }}'
                })
                break;
            case 'warning':
                Toast.fire({
                    icon: 'warning',
                    title: '{{ Session::get('message') }}'
                })
                break;
            case 'error':
                Toast.fire({
                    icon: 'error',
                    title: '{{ Session::get('message') }}'
                })
                break;
        }
    @endif
</script>
@yield('js')

</html>
