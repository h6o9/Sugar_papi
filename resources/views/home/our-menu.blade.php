@extends('home.layout.app')
@section('title', 'Login')
@section('content')
    <style>
        button.tab-scroll-btn {
            padding: 5px;
            background: var(--primary);
            color: #000;
            border-radius: 50px;
            border: none;
            width: 30px;
            height: 30px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .about-us-az-1 { height: 285px; object-fit: cover; }
        .about-us-az-2 { height: 205px; object-fit: cover; }

        @media (max-width: 767px) {
            .about-us-az-1 { height: 225px; }
            .about-us-az-2 { height: 145px; }
        }

        .accordion-button { font-weight: 500; }
        .accordion-button:not(.collapsed) { background-color: #f8f9fa; color: #212529; }
        .accordion-button:focus { box-shadow: none; border-color: transparent; }

        .menu-category-tabs { border-bottom: 2px solid #dee2e6; }
        .menu-category-tabs .nav-item { margin-bottom: -2px; }
        .menu-category-tabs .nav-link {
            color: #6c757d;
            border: none;
            border-bottom: 3px solid transparent;
            padding: 15px 20px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 14px;
            transition: all 0.3s;
        }
        .menu-category-tabs .nav-link:hover,
        .menu-category-tabs .nav-link.active {
            color: #dc3545;
            border-bottom-color: #dc3545;
            background-color: transparent;
        }

        .popular-item {
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
        }
        .popular-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        @media (max-width: 768px) {
            .menu-category-tabs { overflow-x: auto; display: flex; flex-wrap: nowrap; }
            .menu-category-tabs .nav-item { flex-shrink: 0; }
            .menu-category-tabs .nav-link { padding: 10px 15px; font-size: 12px; }
        }

        /* Sliding Tabs */
        .menu-tabs-wrapper { position: relative; }
        .menu-tabs-container { overflow: hidden; position: relative; }
        .menu-category-tabs {
            overflow-x: auto;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            white-space: nowrap;
            display: flex !important;
            gap: 10px;
            flex-wrap: nowrap !important;
        }
        .menu-category-tabs .nav-item { flex-shrink: 0 !important; display: inline-block; }
        .menu-category-tabs .nav-link { white-space: nowrap !important; flex-shrink: 0; }

        .menu-tabs-container .tab-scroll-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: white;
            border: 2px solid #dc3545;
            color: #dc3545;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 10;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .menu-tabs-container .tab-scroll-btn:hover { background: #dc3545; color: white; transform: translateY(-50%) scale(1.1); }
        .menu-tabs-container .tab-scroll-btn.left  { left: -15px; }
        .menu-tabs-container .tab-scroll-btn.right { right: -15px; }
        .menu-tabs-container .tab-scroll-btn.disabled { opacity: 0.3; cursor: not-allowed; }
        .menu-tabs-container .tab-scroll-btn.disabled:hover {
            transform: translateY(-50%) scale(1);
            background: white;
            color: #dc3545;
        }
        .menu-category-tabs::-webkit-scrollbar { display: none; }
        .menu-category-tabs { -ms-overflow-style: none; scrollbar-width: none; }
        .menu-category-tabs .nav-link { white-space: nowrap; padding: 12px 25px !important; }

        /* ✅ Google Places autocomplete z-index fix for modals */
        .pac-container { z-index: 99999 !important; }

        .prodPrice { font-weight: bold; font-size: 1.2rem; }
        .price-display { font-size: 1.2rem; font-weight: bold; }
    </style>

    <div class="modal fade" id="videoModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content rounded-0">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">See it in Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="ratio ratio-16x9">
                        <iframe class="embed-responsive-item" src="" id="video"
                            allowfullscreen allowscriptaccess="always" allow="autoplay"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- =====================================================================
         ✅ CENTRALIZED DISCOUNT HELPER (same logic as example code)
         =====================================================================
         DB structure:
           product.featured_action  = 'decrease'
           product.featured_method  = 'percentage' | 'amount'
           product.featured_amount  = numeric value
           product.original_price   = base price (for simple products)
           product.price            = discounted price (for simple products)
           variant.original_price   = base price (£18, £20)
           variant.price            = discounted/current price (£17, £19)
    --}}
    @php
    function calcDiscount($product) {
        $hasVariants = $product->variants && $product->variants->count() > 0;

        // Pick display variant — prefer 'regular', else first with price > 0
        $displayVariant = null;
        if ($hasVariants) {
            $displayVariant = $product->variants->where('size', 'regular')->first();
            if (!$displayVariant || $displayVariant->price <= 0) {
                $displayVariant = $product->variants->where('price', '>', 0)->first();
            }
            if (!$displayVariant) {
                $displayVariant = $product->variants->first();
            }
        }

        // Prices
        if ($hasVariants && $displayVariant) {
            $originalPrice = floatval($displayVariant->original_price ?? $displayVariant->price);
            $finalPrice    = floatval($displayVariant->price);
        } else {
            $originalPrice = floatval($product->original_price ?? $product->price);
            $finalPrice    = floatval($product->price);
        }

        // Discount badge
        $hasDiscount     = false;
        $badgeText       = '';

        if (
            $product->featured_action == 'decrease' &&
            $originalPrice > $finalPrice &&
            $originalPrice > 0
        ) {
            $hasDiscount = true;
            if ($product->featured_method == 'percentage' && $product->featured_amount > 0) {
                $badgeText = (int) $product->featured_amount . '% OFF';
            } else {
                $badgeText = '£' . number_format($product->featured_amount, 0) . ' OFF';
            }
        }

        return [
            'hasVariants'    => $hasVariants,
            'displayVariant' => $displayVariant,
            'originalPrice'  => $originalPrice,
            'finalPrice'     => $finalPrice,
            'hasDiscount'    => $hasDiscount,
            'badgeText'      => $badgeText,
            'comp'           => optional($product->complementaryProductSingle),
        ];
    }
    @endphp

    {{-- ================================================================
         FULL MENU
         ================================================================ --}}
    <div class="container-xxl py-5">
        <div class="container">

            <div class="wow fadeInUp mb-2" data-wow-delay="0.1s">
                <div id="menuContainer"
                    class="d-flex flex-column align-items-center justify-content-center flex-wrap">
                    <div class="text-center">
                        <h3 class="m-0">
                            @if (!empty($searchTerm))
                                Search Results for "{{ $searchTerm }}"
                            @else
                                Explore Our Complete Menu
                            @endif
                        </h3>
                    </div>

                    @if (empty($searchTerm) && $menuCategories && $menuCategories->isNotEmpty())
                        <div class="w-100 d-flex align-items-center justify-content-end gap-2">
                            <button class="tab-scroll-btn left" onclick="scrollTabs('left')">
                                <span class="ri-arrow-left-line"></span>
                            </button>
                            <button class="tab-scroll-btn right" onclick="scrollTabs('right')">
                                <span class="ri-arrow-right-line"></span>
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ============================================================
                 SEARCH RESULTS
                 ============================================================ --}}
            @if (!empty($searchTerm))

                @if ($filteredProducts->isEmpty())
                    <div class="alert alert-warning text-center">
                        <h5>No items found for "{{ $searchTerm }}"</h5>
                        <p class="mb-0">Please try a different search term.</p>
                    </div>
                @else
                    <div class="row g-4">
                        @foreach ($filteredProducts as $prod)
                        @php $d = calcDiscount($prod); @endphp
                        <div class="col-xl-3 col-lg-4 col-md-6">
                            <a class="popular-item bg-transparent border rounded p-4 d-block text-center h-100 position-relative"
                                href="#" data-bs-toggle="modal"
                                data-bs-target="#menuModalFull-{{ $prod->id }}">

                                {{-- ✅ Discount badge on card --}}
                                @if($d['hasDiscount'])
                                    <span class="badge bg-danger position-absolute top-0 end-0 m-2">
                                        {{ $d['badgeText'] }}
                                    </span>
                                @endif

                                {{-- Product image --}}
                                <div class="text-center mb-3">
                                    <div class="d-flex flex-column align-items-center">
                                        <img class="img-fluid rounded"
                                             src="{{ asset($prod->image) }}"
                                             alt="{{ $prod->name }}"
                                             style="width:130px;height:130px;object-fit:cover;">

                                        {{-- ✅ Complementary product on card --}}
                                        @if(optional($d['comp'])->complementary)
                                            <span style="font-size:22px;font-weight:bold;color:#000;">+</span>
                                            <div class="text-center">
                                                <img class="img-fluid rounded"
                                                     src="{{ asset($d['comp']->complementary->image) }}"
                                                     alt="{{ $d['comp']->complementary->name }}"
                                                     style="width:100px;height:100px;object-fit:cover;">
                                                <br>
                                                <span class="badge bg-success m-2">BUY 1 GET 1 FREE</span>
                                                <p class="mb-0 small mt-1 fw-medium text-dark">{{ $d['comp']->complementary->name }}</p>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                {{-- Name + Price --}}
                                <div class="mb-2">
                                    <h5 class="mb-1 main-heading text-center">{{ $prod->name }}</h5>
                                    <p class="text-center mb-2">
                                        @if($d['hasDiscount'])
                                            <span class="text-muted text-decoration-line-through small d-block">
                                                £{{ number_format($d['originalPrice'], 2) }}
                                            </span>
                                        @endif
                                        <span class="badge bg-primary">
                                            {{ $d['hasVariants'] ? 'From ' : '' }}£{{ number_format($d['finalPrice'], 2) }}
                                        </span>
                                    </p>
                                </div>

                                <p class="mb-0 text-muted small text-center">{!! $prod->description !!}</p>
                            </a>
                        </div>
                        @endforeach
                    </div>
                @endif

            {{-- ============================================================
                 NORMAL MENU (tabbed)
                 ============================================================ --}}
            @else
                @if ($menuCategories && $menuCategories->isNotEmpty())

                    {{-- Tab nav --}}
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="menu-tabs-wrapper">
                                <div class="menu-tabs-container">
                                    <ul class="nav nav-tabs nav-justified menu-category-tabs"
                                        id="menuTabs" role="tablist">
                                        @foreach ($menuCategories as $index => $menuCat)
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link @if($index==0) active @endif"
                                                    id="tab{{ $menuCat->id }}"
                                                    data-bs-toggle="tab"
                                                    data-bs-target="#menuTab{{ $menuCat->id }}"
                                                    type="button" role="tab"
                                                    aria-controls="menuTab{{ $menuCat->id }}"
                                                    aria-selected="{{ $index == 0 ? 'true' : 'false' }}">
                                                    {{ $menuCat->name }}
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Tab content --}}
                    <div class="tab-content" id="menuContent">
                        @foreach ($menuCategories as $index => $menuCat)
                            <div class="tab-pane fade @if($index==0) show active @endif"
                                id="menuTab{{ $menuCat->id }}" role="tabpanel"
                                aria-labelledby="tab{{ $menuCat->id }}">

                                @if ($menuCat->product && $menuCat->product->isNotEmpty())
                                    <div class="row g-4">
                                        @foreach ($menuCat->product as $prod)
                                        @php $d = calcDiscount($prod); @endphp
                                        <div class="col-xl-3 col-lg-4 col-md-6">
                                            <a class="popular-item bg-transparent border rounded p-4 d-block text-center h-100 position-relative"
                                                href="#" data-bs-toggle="modal"
                                                data-bs-target="#menuModalFull-{{ $prod->id }}">

                                                {{-- ✅ Discount badge --}}
                                                @if($d['hasDiscount'])
                                                    <span class="badge bg-danger position-absolute top-0 end-0 m-2">
                                                        {{ $d['badgeText'] }}
                                                    </span>
                                                @endif

                                                <div class="text-center mb-3">
                                                    <div class="d-flex flex-column align-items-center">
                                                        <img class="img-fluid rounded"
                                                             src="{{ asset($prod->image) }}"
                                                             alt="{{ $prod->name }}"
                                                             style="width:130px;height:130px;object-fit:cover;">

                                                        {{-- ✅ Complementary product --}}
                                                        @if(optional($d['comp'])->complementary)
                                                            <span style="font-size:22px;font-weight:bold;color:#000;">+</span>
                                                            <div class="text-center">
                                                                <img class="img-fluid rounded"
                                                                     src="{{ asset($d['comp']->complementary->image) }}"
                                                                     alt="{{ $d['comp']->complementary->name }}"
                                                                     style="width:100px;height:100px;object-fit:cover;">
                                                                <br>
                                                                <span class="badge bg-success m-2">BUY 1 GET 1 FREE</span>
                                                                <p class="mb-0 small mt-1 fw-medium text-dark">{{ $d['comp']->complementary->name }}</p>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>

                                                <div class="mb-2">
                                                    <h5 class="mb-1 main-heading text-center">{{ $prod->name }}</h5>
                                                    <p class="text-center mb-2">
                                                        @if($d['hasDiscount'])
                                                            <span class="text-muted text-decoration-line-through small d-block">
                                                                £{{ number_format($d['originalPrice'], 2) }}
                                                            </span>
                                                        @endif
                                                        <span class="badge bg-primary">
                                                            {{ $d['hasVariants'] ? 'From ' : '' }}£{{ number_format($d['finalPrice'], 2) }}
                                                        </span>
                                                    </p>
                                                </div>

                                                <p class="mb-0 text-muted small text-center">{!! $prod->description !!}</p>
                                            </a>
                                        </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="alert alert-warning text-center">
                                        <h5>No products found in {{ $menuCat->name }}!</h5>
                                        <p class="mb-0">We're currently updating this section. Please check back soon!</p>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                @else
                    <div class="alert alert-warning text-center">
                        <h5>No menu categories found!</h5>
                        <p class="mb-0">We're currently updating the menu. Please check back soon!</p>
                    </div>
                @endif
            @endif

        </div>
    </div>
    {{-- FULL MENU END --}}

    {{-- ================================================================
         MODALS — generated from all categories
         (covers both search results & normal menu items)
         ================================================================ --}}
    @if ($menuCategories && $menuCategories->isNotEmpty())
        @foreach ($menuCategories as $menuCat)
            @if ($menuCat->product && $menuCat->product->isNotEmpty())
                @foreach ($menuCat->product as $prod)
                @php $d = calcDiscount($prod); @endphp

                <div class="container-fluid cart food-modal wow fadeIn" data-wow-delay="0.1s">
                    <div class="modal fade menu-modal" id="menuModalFull-{{ $prod->id }}"
                        tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-body p-0 scrollable">

                                    <input type="hidden" name="product_id" value="{{ $prod->id }}">

                                    {{-- Product image + discount badge --}}
                                    <div class="position-relative">
                                        <img class="w-100" src="{{ asset($prod->image) }}" alt="product-img">
                                        @if($d['hasDiscount'])
                                            <span class="badge bg-danger position-absolute top-0 end-0 m-3 fs-6">
                                                {{ $d['badgeText'] }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="p-3 description">
                                        <h3>{{ $prod->name }}</h3>

                                        {{-- ✅ Complementary product in modal --}}
                                        @if(optional($d['comp'])->complementary)
                                            <input type="hidden"
                                                value="{{ $d['comp']->complementary->id }}"
                                                name="complementary_id">
                                            <div class="mt-3 text-center">
                                                <img class="img-fluid rounded-circle"
                                                     src="{{ asset($d['comp']->complementary->image) }}"
                                                     alt="{{ $d['comp']->complementary->name }}"
                                                     style="width:100px;height:100px;object-fit:cover;">
                                                <br>
                                                <span class="badge bg-success m-2">BUY 1 GET 1 FREE</span>
                                                <p class="mb-0 small fw-medium text-dark">
                                                    {{ $d['comp']->complementary->name }}
                                                </p>
                                            </div>
                                        @endif

                                        {{-- ✅ PRICE DISPLAY — variant or simple, with/without discount --}}
                                        @if($d['hasVariants'])
                                            {{--
                                                variant-original-price: shows when variant.original_price > variant.price
                                                JS will update both spans on select change & on modal open.
                                                We hide it by default if no discount on the first variant.
                                            --}}
                                            <p class="price-display mb-1">
                                                <span class="text-muted text-decoration-line-through d-block variant-original-price"
                                                    @if(!($d['originalPrice'] > 0 && $d['originalPrice'] > $d['finalPrice']))
                                                        style="display:none!important"
                                                    @endif>
                                                    £{{ number_format($d['originalPrice'], 2) }}
                                                </span>
                                                £ <span class="prodPrice">{{ number_format($d['finalPrice'], 2) }}</span>
                                            </p>

                                            {{--
                                                ✅ KEY FIX: data-original attribute on each option
                                                so JS can read variant.original_price for strikethrough
                                            --}}
                                            <select class="form-control bg-white ps-1 select-size"
                                                    name="variant_id" style="appearance:auto">
                                                @foreach ($prod->variants as $variant)
                                                    <option
                                                        value="{{ $variant->id }} {{ number_format((float)$variant->price, 2) }}"
                                                        data-original="{{ number_format((float)($variant->original_price ?? 0), 2) }}"
                                                        {{ $loop->first ? 'selected' : '' }}>
                                                        {{ $variant->size }} – £{{ number_format((float)$variant->price, 2) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <h6 class="small mt-1 mb-3">Note: Prices vary depending on the selected size</h6>

                                        @else
                                            {{-- Simple product --}}
                                            @if($d['hasDiscount'])
                                                <p>
                                                    <span class="text-muted text-decoration-line-through">
                                                        £{{ number_format($d['originalPrice'], 2) }}
                                                    </span><br>
                                                    <span class="text-danger fw-bold prodPrice">
                                                        £{{ number_format($d['finalPrice'], 2) }}
                                                    </span>
                                                </p>
                                            @else
                                                <p>£ <span class="prodPrice">{{ number_format($d['finalPrice'], 2) }}</span></p>
                                            @endif
                                        @endif
                                        {{-- END price display --}}

                                        <p class="small">{!! $prod->description !!}</p>

                                        <div class="d-flex cart-btn">
                                            <button class="btn p-0 decrement" type="button">-</button>
                                            <input type="text"
                                                class="cart_input increment-input text-center"
                                                value="1" name="quantity"
                                                id="quantity_{{ $prod->id }}">
                                            <button class="btn p-0 increment" type="button">+</button>
                                        </div>
                                    </div>

                                    {{-- How to get it --}}
                                    <div class="description p-3">
                                        <div class="d-flex justify-content-between">
                                            <h6>How to get it</h6>
                                            <h6 class="text-danger">Required</h6>
                                        </div>
                                        @foreach ($branches as $branchIndex => $branch)
                                            @if ($branch->status == 1)
                                                <div class="branch-option mb-3">
                                                    <input type="hidden" name="branch_id" value="{{ $branch->id }}">

                                                    {{-- Store Pickup --}}
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio"
                                                            name="status_{{ $prod->id }}"
                                                            id="pickupStatus{{ $prod->id }}_{{ $branch->id }}_{{ $branchIndex }}"
                                                            value="1" checked
                                                            onchange="toggleDelivery('{{ $prod->id }}', '{{ $branch->id }}_{{ $branchIndex }}')">
                                                        <label class="form-check-label fw-bold small"
                                                            for="pickupStatus{{ $prod->id }}_{{ $branch->id }}_{{ $branchIndex }}">
                                                            Store Pickup
                                                        </label>
                                                    </div>
                                                    <p class="small fw-bold m-0 sel-location mt-1"
                                                        id="storePickupSection{{ $prod->id }}_{{ $branch->id }}_{{ $branchIndex }}">
                                                        <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($branch->location) }}"
                                                            target="_blank" style="text-decoration:none;color:inherit;">
                                                            {{ $branch->location }}
                                                        </a>
                                                    </p>

                                                    {{-- Home Delivery --}}
                                                    <div class="form-check mt-3">
                                                        <input class="form-check-input" type="radio"
                                                            name="status_{{ $prod->id }}"
                                                            id="homeStatus{{ $prod->id }}_{{ $branch->id }}_{{ $branchIndex }}"
                                                            value="2"
                                                            onchange="toggleDelivery('{{ $prod->id }}', '{{ $branch->id }}_{{ $branchIndex }}')">
                                                        <label class="form-check-label fw-bold small"
                                                            for="homeStatus{{ $prod->id }}_{{ $branch->id }}_{{ $branchIndex }}">
                                                            Home Delivery
                                                        </label>
                                                    </div>
                                                    <div id="deliveryAddressField{{ $prod->id }}_{{ $branch->id }}_{{ $branchIndex }}"
                                                        class="mt-2" style="display:none;">
                                                        {{-- ✅ Google Autocomplete input with data attributes --}}
                                                        <input type="text"
                                                            id="deliveryInput{{ $prod->id }}_{{ $branch->id }}"
                                                            name="delivery_address_{{ $prod->id }}"
                                                            class="form-control location-input"
                                                            data-product="{{ $prod->id }}"
                                                            data-branch="{{ $branch->id }}"
                                                            placeholder="Enter your delivery address"
                                                            autocomplete="off" />
                                                        <input type="hidden" name="lat_{{ $prod->id }}"
                                                            id="lat{{ $prod->id }}_{{ $branch->id }}">
                                                        <input type="hidden" name="lng_{{ $prod->id }}"
                                                            id="lng{{ $prod->id }}_{{ $branch->id }}">
                                                    </div>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>

                                    {{-- Toppings --}}
                                    @if ($prod->category && $prod->category->isNotEmpty())
                                        @foreach ($prod->category as $toppingIndex => $category)
                                            <div class="description p-3">
                                                <div class="arrow" style="cursor:pointer"
                                                    data-bs-toggle="collapse"
                                                    data-bs-target="#toppingFull{{ $toppingIndex }}{{ $category->id }}{{ $prod->id }}">
                                                    <div class="d-flex justify-content-between">
                                                        <h6 class="m-0">{{ $category->getCategory->name }}</h6>
                                                        <h6 class="fw-normal m-0 d-flex align-items-center">
                                                            Optional
                                                            <span class="h5 m-0 p-0 ri-arrow-up-s-line"></span>
                                                        </h6>
                                                    </div>
                                                </div>
                                                <div class="collapse show"
                                                    id="toppingFull{{ $toppingIndex }}{{ $category->id }}{{ $prod->id }}">
                                                    @php
                                                        $categoryToppings = App\Models\CategoryTopping::where(
                                                            'category_id', $category->getCategory->id
                                                        )->get();
                                                    @endphp
                                                    @foreach ($categoryToppings as $categoryTopping)
                                                        <div class="d-flex justify-content-between">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox"
                                                                    name="toppings[]"
                                                                    id="toppingchekFull{{ $toppingIndex }}{{ $category->id }}{{ $categoryTopping->topping->id }}{{ $prod->id }}"
                                                                    value="{{ $categoryTopping->topping->id }}"
                                                                    data-category-id="{{ $category->getCategory->id }}">
                                                                <label class="form-check-label m-0"
                                                                    for="toppingchekFull{{ $toppingIndex }}{{ $category->id }}{{ $categoryTopping->topping->id }}{{ $prod->id }}">
                                                                    {{ $categoryTopping->topping->name }}
                                                                </label>
                                                            </div>
                                                            <p class="m-0">
                                                                {{ isset($categoryTopping->topping->price) ? '£'.$categoryTopping->topping->price : '' }}
                                                            </p>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    @endif

                                </div>{{-- /modal-body --}}

                                <div class="modal-footer position-relative px-2">
                                    <button type="button"
                                        style="font-size:24px;position:absolute;left:0;width:30px;height:30px;display:flex;justify-content:center;align-items:center"
                                        class="btn time-modal-close ri-close-circle-line btn-danger px-2 ms-3 py-0"
                                        data-bs-dismiss="modal"></button>
                                    <div class="text-center mx-auto">
                                        <button class="btn btn-danger addto-cart px-sm-5 px-4">
                                            Add To Order
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @endforeach
            @endif
        @endforeach
    @endif

@endsection

@section('js')

@if (\Illuminate\Support\Facades\Session::has('message'))
    <script>toastr.success('{{ \Illuminate\Support\Facades\Session::get('message') }}');</script>
@endif

<script>
$(function () {

    // =========================================================
    // 1. VARIANT SELECT CHANGE → update price + strikethrough
    //    ✅ Reads data-original from option for variant.original_price
    // =========================================================
    $(document).on('change', '.select-size', function () {
        var selectedOption = $(this).find('option:selected');
        var parts          = $(this).val().trim().split(' ');
        var price          = parseFloat(parts[parts.length - 1]);           // variant.price
        var originalPrice  = parseFloat(selectedOption.data('original')) || 0; // variant.original_price
        var $modalBody     = $(this).closest('.modal-body');

        // Update current price
        $modalBody.find('.prodPrice').text(price.toFixed(2));

        // ✅ Show/hide strikethrough original price
        var $strikeEl = $modalBody.find('.variant-original-price');
        if (originalPrice > 0 && originalPrice > price) {
            $strikeEl.text('£' + originalPrice.toFixed(2)).removeAttr('style').show();
        } else {
            $strikeEl.text('').hide();
        }
    });

    // =========================================================
    // 2. MODAL OPEN → initialise price from pre-selected variant
    //    ✅ Uses shown.bs.modal (fires after animation, DOM ready)
    // =========================================================
    $(document).on('shown.bs.modal', '.menu-modal', function () {
        var $select = $(this).find('.select-size');
        if (!$select.length) return;

        var $selectedOpt  = $select.find('option:selected');
        var parts         = $selectedOpt.val().trim().split(' ');
        var price         = parseFloat(parts[parts.length - 1]);
        var originalPrice = parseFloat($selectedOpt.data('original')) || 0;

        $(this).find('.prodPrice').text(price.toFixed(2));

        var $strikeEl = $(this).find('.variant-original-price');
        if (originalPrice > 0 && originalPrice > price) {
            $strikeEl.text('£' + originalPrice.toFixed(2)).removeAttr('style').show();
        } else {
            $strikeEl.text('').hide();
        }
    });

    // =========================================================
    // 3. INCREMENT / DECREMENT
    // =========================================================
    $(document).on('click', '.increment', function () {
        var $input = $(this).siblings('.cart_input');
        $input.val(parseInt($input.val()) + 1);
    });

    $(document).on('click', '.decrement', function () {
        var $input = $(this).siblings('.cart_input');
        var val    = parseInt($input.val()) - 1;
        $input.val(val >= 1 ? val : 1);
    });

    // =========================================================
    // 4. ADD TO ORDER
    // =========================================================
    $(document).on('click', '.addto-cart', function () {
        var $btn      = $(this);
        var $modal    = $btn.closest('.food-modal');

        var productId = $modal.find('input[name="product_id"]').val();
        var quantity  = $modal.find('input[name="quantity"]').val() || 1;
        var branchId  = $modal.find('input[name="branch_id"]').first().val();

        var complementaryId = $modal.find('input[name="complementary_id"]').length
            ? $modal.find('input[name="complementary_id"]').val()
            : null;

        if (!productId) { toastr.error('Product not found.'); return; }

        var deliveryStatus  = $modal.find('input[name^="status_"]:checked').val() || '1';
        var deliveryAddress = '', lat = '', lng = '';

        if (deliveryStatus == '2') {
            deliveryAddress = $modal.find('input[name="delivery_address_' + productId + '"]').val();
            lat             = $modal.find('input[name="lat_' + productId + '"]').val();
            lng             = $modal.find('input[name="lng_' + productId + '"]').val();

            if (!deliveryAddress) {
                toastr.error('Please enter delivery address');
                return;
            }
            if (!lat || !lng) {
                toastr.error('Please select a valid address from the suggestions');
                return;
            }
        }

        // Variant
        var variantId  = '';
        var $variantSel = $modal.find('select[name="variant_id"]');
        if ($variantSel.length && $variantSel.val()) {
            variantId = $variantSel.val().trim().split(' ')[0];
        }

        // Toppings by category
        var toppingsByCategory = {};
        $modal.find('input[name="toppings[]"]:checked').each(function () {
            var catId = $(this).data('category-id');
            if (!toppingsByCategory[catId]) toppingsByCategory[catId] = [];
            toppingsByCategory[catId].push($(this).val());
        });
        var toppingsArray = Object.entries(toppingsByCategory).map(function (entry) {
            return { category_id: entry[0], toppings: entry[1] };
        });

        $btn.prop('disabled', true).text('Adding...');

        $.ajax({
            type: 'POST',
            url: '{{ route("add.to.cart") }}',
            data: {
                _token:               '{{ csrf_token() }}',
                product_id:           productId,
                quantity:             quantity,
                branch_id:            branchId,
                variant_id:           variantId,
                delivery_status:      deliveryStatus,
                delivery_address:     deliveryAddress,
                lat:                  lat,
                lng:                  lng,
                complementary_id:     complementaryId,
                location:             deliveryStatus,
                toppings_by_category: toppingsArray,
            },
            success: function (response) {
                if (response.success) {
                    toastr.success('Product added to cart!');
                    var cartCount = Object.keys(response.cart).length;
                    $('.cart-counter-1').text(cartCount);
                    updateCartUI(response);
                    if (cartCount > 0) {
                        $('a[href*="my-cart"]').removeClass('disabled').prop('disabled', false);
                    }
                    $btn.closest('.modal').modal('hide');
                } else {
                    toastr.error(response.message || 'Something went wrong.');
                }
            },
            error: function (xhr) {
                console.error('Cart error:', xhr.responseText);
                toastr.error('Error: ' + (xhr.responseJSON?.message || 'Server error'));
            },
            complete: function () {
                $btn.prop('disabled', false).text('Add To Order');
            }
        });
    });

    // =========================================================
    // 5. UPDATE CART UI
    // =========================================================
    function updateCartUI(data) {
        var cartItemCount = 0;
        var html = '';

        $.each(data.cart, function (key, product) {
            cartItemCount += parseInt(product.quantity);

            html += '<div class="carting-child px-3 mt-3 d-flex justify-content-between pb-3 border-bottom" id="' + product.product_id + 'carted">';
            html += '<img src="' + product.image + '" alt="">';
            html += '<div class="content">';
            html += '<div class="d-flex cart-input-parent justify-content-between">';
            html += '<h6 class="m-0">' + product.name;
            html += product.size ? ' (<span style="font-size:12px">' + product.size + '</span>)' : '';
            html += '</h6>';
            html += '<h6 class="m-0 total-price">£' + (parseFloat(product.price) * product.quantity).toFixed(2) + '</h6>';
            html += '<p class="product-price d-none">' + product.price + '</p>';
            html += '</div>';

            html += '<div class="delivery-info mb-2">';
            html += '<p class="small m-0 text-' + (product.delivery_status == '2' ? 'info' : 'success') + '">';
            html += product.delivery_status == '2' ? 'Home Delivery' : 'Store Pickup';
            html += '</p>';
            if (product.delivery_status == '2' && product.delivery_address) {
                html += '<p class="small m-0">Delivery to: ' + product.delivery_address + '</p>';
            }
            html += '</div>';

            html += '<div class="mb-2"><h6 class="m-0">Toppings</h6>';
            if (product.toppingsName_by_categoryName && product.toppingsName_by_categoryName.length) {
                $.each(product.toppingsName_by_categoryName, function (i, cat) {
                    html += '<div class="mb-2">';
                    html += '<p class="mb-1 fw-bold text-black">' + cat.category_name + '</p>';
                    $.each(cat.topping_names, function (j, name) {
                        html += '<p class="small m-0">' + name + '</p>';
                    });
                    html += '</div>';
                });
            }
            html += '</div>';

            html += '<div class="cart-btn">';
            html += '<button class="btn decrement-btn p-0" data-product-id="' + product.product_id + ',' + product.variant_id + '">-</button>';
            html += '<input type="number" name="quantity" value="' + product.quantity + '" class="increment-input cart-input cart_input text-center">';
            html += '<button class="btn increment-btn p-0" data-product-id="' + product.product_id + ',' + product.variant_id + '">+</button>';
            html += '<p id="' + product.product_id + '" class="d-none sibling-p"></p>';
            html += '</div></div></div>';
        });

        $('.cart-counter-1').text(cartItemCount);
        $('.cards-parent').html(html);
        if (cartItemCount > 0) $('.button-disable').removeClass('disabled');
    }

    // =========================================================
    // 6. TOPPING ARROW TOGGLE
    // =========================================================
    $(document).on('click', '.arrow', function () {
        var $icon = $(this).find('span');
        $icon.toggleClass('ri-arrow-up-s-line ri-arrow-down-s-line');
    });

    // =========================================================
    // 7. UPDATE LOCATION BUTTON
    // =========================================================
    $(document).on('click', '.updateLocationBtn', function () {
        var $selected = $('input[name="choosen_location"]:checked');
        if (!$selected.length) { alert('Please select a location.'); return; }
        $.ajax({
            type: 'POST',
            url: '{{ route("update.branch.status") }}',
            data: { _token: '{{ csrf_token() }}', branch_id: $selected.data('branch-id') },
            success: function () {
                toastr.success('Location Updated Successfully');
                setTimeout(function () { location.reload(); }, 1000);
            },
            error: function (error) { console.error('Error updating branch status:', error); }
        });
    });

    // =========================================================
    // 8. TAB SCROLL
    // =========================================================
    window.scrollTabs = function (direction) {
        var tabs = document.querySelector('.menu-category-tabs');
        if (tabs) tabs.scrollBy({ left: direction === 'left' ? -200 : 200, behavior: 'smooth' });
    };

    (function () {
        var tabs     = document.querySelector('.menu-category-tabs');
        var leftBtn  = document.querySelector('.tab-scroll-btn.left');
        var rightBtn = document.querySelector('.tab-scroll-btn.right');
        if (!tabs || !leftBtn || !rightBtn) return;

        function updateBtns() {
            if (tabs.scrollWidth <= tabs.clientWidth) {
                leftBtn.style.display = rightBtn.style.display = 'none';
                return;
            }
            leftBtn.classList.toggle('disabled', tabs.scrollLeft === 0);
            rightBtn.classList.toggle('disabled',
                tabs.scrollLeft + tabs.clientWidth >= tabs.scrollWidth - 10);
        }
        tabs.addEventListener('scroll', updateBtns);
        updateBtns();
    })();

}); // end $(function)

// =========================================================
// 9. TOGGLE DELIVERY (global function — called from onchange)
//    ✅ branchUnique = "branchId_branchIndex" for unique IDs
// =========================================================
function toggleDelivery(productId, branchUnique) {
    var pickupRadio   = document.getElementById('pickupStatus'         + productId + '_' + branchUnique);
    var homeRadio     = document.getElementById('homeStatus'           + productId + '_' + branchUnique);
    var pickupSection = document.getElementById('storePickupSection'   + productId + '_' + branchUnique);
    var deliveryField = document.getElementById('deliveryAddressField' + productId + '_' + branchUnique);

    if (!homeRadio || !pickupRadio) return;

    if (homeRadio.checked) {
        if (pickupSection) pickupSection.style.display = 'none';
        if (deliveryField) deliveryField.style.display = 'block';
    } else {
        if (pickupSection) pickupSection.style.display  = 'block';
        if (deliveryField) {
            deliveryField.style.display = 'none';
            var inp = deliveryField.querySelector('input[type="text"]');
            if (inp) inp.value = '';
        }
    }
}
</script>

{{-- =========================================================
     GOOGLE MAPS AUTOCOMPLETE
     ✅ pac-container z-index fix so modal doesn't swallow clicks
     ========================================================= --}}
<script async defer
    src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBUMK9qFdsbuuuTMiaPHCJok4Rro91yvaE&libraries=places&callback=initAllAutocomplete&loading=async">
</script>

<script>
window.initAllAutocomplete = function () {
    bindAutocomplete(document);

    // Re-bind when any modal opens (new inputs appear in DOM)
    document.addEventListener('shown.bs.modal', function (e) {
        bindAutocomplete(e.target);

        // ✅ Prevent Bootstrap modal from stealing pac-container clicks
        e.target.addEventListener('mousedown', function preventModalSwallow(ev) {
            if (ev.target.closest('.pac-container')) {
                ev.stopPropagation();
            }
        }, true);
    });
};

function bindAutocomplete(container) {
    container.querySelectorAll('.location-input').forEach(function (input) {
        if (input.dataset.autocompleteInit === '1') return; // skip already bound
        input.dataset.autocompleteInit = '1';

        var productId = input.dataset.product;
        var branchId  = input.dataset.branch;
        var latField  = document.getElementById('lat' + productId + '_' + branchId);
        var lngField  = document.getElementById('lng' + productId + '_' + branchId);

        var autocomplete = new google.maps.places.Autocomplete(input, {
            fields: ['geometry', 'formatted_address'],
            types:  ['geocode'],
            componentRestrictions: { country: 'gb' }
        });

        // Clear lat/lng if user types manually (no suggestion selected yet)
        input.addEventListener('input', function () {
            if (latField) latField.value = '';
            if (lngField) lngField.value = '';
        });

        // ✅ Set lat/lng when a suggestion is chosen
        autocomplete.addListener('place_changed', function () {
            var place = autocomplete.getPlace();
            if (!place || !place.geometry) return;
            if (latField) latField.value = place.geometry.location.lat();
            if (lngField) lngField.value = place.geometry.location.lng();
            if (place.formatted_address) input.value = place.formatted_address;
        });

        // ✅ Validate on blur — clear everything if no valid selection was made
        input.addEventListener('blur', function () {
            setTimeout(function () {
                if (!latField || !latField.value || !lngField || !lngField.value) {
                    input.value = '';
                    if (latField) latField.value = '';
                    if (lngField) lngField.value = '';
                }
            }, 400); // 400ms so pac-item click registers first
        });
    });
}
</script>

@endsection