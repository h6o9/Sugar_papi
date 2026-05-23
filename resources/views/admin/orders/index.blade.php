@extends('admin.layout.app')
@section('title', 'index')
@section('content')
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<div class="main-content" style="min-height: 562px;">
    <section class="section">
        <div class="section-body">
            <div class="row">
                <div class="col-12 col-md-12 col-lg-12">
                    <div class="card">
                        <div class="card-header">
                            <div class="col-12">
                                <h4>Orders</h4>
                            </div>
                        </div>
                        <div class="card-body  table-responsive">
                            <table class="table table-striped table-bordered text-center" id="table_id_events">
                                <thead>
                                    <tr>
                                        <th>Sr.</th>
                                        <th>Order Code</th>
                                        <th>User Name</th>
                                        <th>Product Name</th>
                                        <th>Buy One Get One Free</th>
                                        <th>Vehicle Color</th>
                                        <th>Vehicle Number</th>
                                        {{-- <th>Branch No</th> --}}
                                        <th>Branch Location</th>
                                        <th>Branch Name</th>
                                        <th>Topping</th>
                                        <th>Product Size</th>
                                        <th>Product Price</th>
                                        <th>Quantity</th>
                                        <th>Sub Total</th>
                                        <th>Tip Amount</th>
                                        <th>Branch Tax</th>
                                        <th>Delivery Charges</th>
                                        <th>Stripe Fee</th>
                                        <th>Order Total</th>
                                        <th>After Redeemed</th>
                                        <th>Redeemed</th>
                                        <th>Status</th>
                                        <th>Change Status</th>
                                    </tr>
                                </thead>
                                <tbody>

                                    @foreach ($orders as $order)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>#{{ $order->code }}</td>
                                        <th>{{ $order->user->name }}</th>
                                        <td>
                                            @foreach ($order->orderItem as $orderItem)
                                            {{ $orderItem->product_name }}
                                            @if (!$loop->last)
                                            ,
                                            @endif
                                            @endforeach
                                        </td>
                                        <td>
                                            @php $hasComplementary = false; @endphp

                                            @foreach ($order->orderItem as $item)
                                                @if ($item->complementaryProduct)
                                                    {{ $item->complementaryProduct->name }}
                                                    @if (!$loop->last)
                                                    ,
                                                    @endif
                                                    @php $hasComplementary = true; @endphp
                                                @endif
                                            @endforeach

                                            @if (!$hasComplementary)
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            @if ($order->vehicle_color != 'NULL')
                                            {{ $order->vehicle_color }}
                                            @else
                                            <div class="badge p-2 badge-shadow btn-danger text-white">
                                                No Vehicle Color
                                            </div>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($order->vehicle_number != 'NULL')
                                            {{ $order->vehicle_number }}
                                            @else
                                            <div class="badge p-2 badge-shadow btn-danger text-white">
                                                No Vehicle Number
                                            </div>
                                            @endif
                                        </td>
                                        {{-- <td>
                                            @php
                                            $branchNumbers = [];
                                            @endphp

                                            @foreach ($order->orderItem as $orderItem)
                                            @php
                                            $branchNumber = $orderItem->branch->branch_number;
                                            @endphp

                                            @if (!in_array($branchNumber, $branchNumbers))
                                            {{ $branchNumber }}
                                            @php
                                            $branchNumbers[] = $branchNumber;
                                            @endphp
                                            @endif
                                            @endforeach
                                        </td> --}}
                                        <td>
                                            @php
                                            $displayLocations = [];
                                            @endphp

                                            @foreach ($order->orderItem as $orderItem)
                                            @php
                                            $isDelivery = in_array((string) ($orderItem->delivery_status ?? ''), ['2', 'delivery'], true);
                                            if ($isDelivery && !empty($orderItem->delivery_address)) {
                                                $locationLabel = $orderItem->delivery_address;
                                            } else {
                                                $locationLabel = $orderItem->branch && $orderItem->branch->location
                                                    ? $orderItem->branch->location
                                                    : ($orderItem->delivery_address ?: 'N/A');
                                            }
                                            @endphp

                                            @if (!in_array($locationLabel, $displayLocations))
                                            {{ $locationLabel }}
                                            @php
                                            $displayLocations[] = $locationLabel;
                                            @endphp
                                            @endif
                                            @endforeach
                                        </td>
                                        <td>
                                            @php
                                            $branchNames = [];
                                            @endphp

                                            @foreach ($order->orderItem as $orderItem)
                                            @php
                                            $branchName = $orderItem->branch ? $orderItem->branch->name : 'N/A';
                                            @endphp

                                            @if (!in_array($branchName, $branchNames))
                                            {{ $branchName }}
                                            @php
                                            $branchNames[] = $branchName;
                                            @endphp
                                            @endif
                                            @endforeach
                                        </td>
                                        <td>
                                            @foreach ($order->orderItem as $orderItem)
                                            @if (!$orderItem->orderToppings->isEmpty())
                                            @foreach ($orderItem->orderToppings as $topping)
                                            @if ($loop->first)
                                            <h6 class="small m-0">{{ $topping->category->name }}:
                                            </h6>
                                            @endif
                                            <p class="small m-0">
                                                {{ $topping->toppings->name }}
                                                (£{{ $topping->toppings->price }})
                                            </p>
                                            @endforeach
                                            @else
                                            <div class="badge p-2 badge-shadow btn-danger text-white">
                                                No Topping
                                            </div>
                                            @endif
                                            @endforeach
                                        </td>

                                        <td>
                                            @foreach ($order->orderItem as $orderItem)
                                            {{ $orderItem->product_size ? $orderItem->product_size : 'No Size Found' }}
                                            @break
                                            @endforeach
                                        </td>
                                        <td>
                                            @foreach ($order->orderItem as $orderItem)
                                            £{{ $orderItem->product_price }}
                                            @if (!$loop->last)
                                            ,
                                            @endif
                                            @endforeach
                                        </td>
                                        <td>
                                            @foreach ($order->orderItem as $orderItem)
                                            {{ $orderItem->quantity }}
                                            @if (!$loop->last)
                                            ,
                                            @endif
                                            @endforeach
                                        </td>
                                        <td>
                                            @foreach ($order->orderItem as $orderItem)
                                            £{{ number_format($orderItem->sub_total, 2) }}
                                            @if (!$loop->last)
                                            ,
                                            @endif
                                            @endforeach
                                        </td>
                                        <td>
                                            @php
                                            $tips = [];
                                            @endphp

                                            @foreach ($order->orderItem as $orderItem)
                                            @php
                                            $tip = $orderItem->tip;
                                            @endphp

                                            @if (!in_array($tip, $tips))
                                            £ {{ $tip }}
                                            @php
                                            $tips[] = $tip;
                                            @endphp
                                            @endif
                                            @endforeach
                                        </td>
                                        <td>
                                            @php
                                            $branchTaxs = [];
                                            @endphp

                                            @foreach ($order->orderItem as $orderItem)
                                            @php
                                            $branchTax = $orderItem->branch ? $orderItem->branch->tax : 0;
                                            @endphp

                                            @if (!in_array($branchTax, $branchTaxs))
                                            £ {{ $branchTax }}
                                            @php
                                            $branchTaxs[] = $branchTax;
                                            @endphp
                                            @endif
                                            @endforeach
                                        </td>
                                        <td>£{{ $order->delivery_charge }}</td>
                                        <td>£{{ number_format($order->gateway_fee ?? 0, 2) }}</td>
                                        <td>£{{ number_format($order->total_amount ?? 0, 2) }}</td>
                                        @php
                                        $redeemedAmount = is_numeric($order->redeemed) ? $order->redeemed : 0;
                                        $afterRedeemed = $order->total_amount - $redeemedAmount;
                                        @endphp
                                        @if($afterRedeemed < $order->total_amount)
                                        <td>£{{ $afterRedeemed }}</td>
                                        @else
                                        <td>-</td>
                                        @endif
                                        @if($redeemedAmount > 0)
                                        <td>£{{ $redeemedAmount }}</td>
                                        @else
                                        <td>-</td>
                                        @endif
                                        <td>
                                            @if ($order->status === 'Pending')
                                            <div class="badge p-2 badge-shadow btn-danger text-white">
                                                Pending
                                            </div>
                                            @elseif($order->status === 'Order Ready')
                                            <div class="badge p-2 badge-shadow btn-warning text-white">
                                                Order Ready
                                            </div>
                                            @elseif($order->status === 'Delivered')
                                            <div class="badge p-2 badge-success badge-shadow">Delivered</div>
                                            @endif
                                        </td>
                                           <td>
                                            <div class="dropdown">
                                                <form
                                                    action="{{ route('update-order-status', ['orderId' => $order->id]) }}"
                                                    method="post">
                                                    @csrf
                                                    <select class="form-select" name="status"
                                                        aria-label="Default select example"
                                                        onchange="this.form.submit()">
                                                        <option value="Pending" {{ $order->status === 'Pending' ?
                                                            'selected' : '' }}>
                                                            Pending</option>
                                                        <option value="Order Ready" {{ $order->status === 'Order Ready'
                                                            ? 'selected' : '' }}>
                                                            Order ready</option>
                                                        <option value="Delivered" {{ $order->status === 'Delivered' ?
                                                            'selected' : '' }}>
                                                            Delivered</option>
                                                    </select>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
@section('js')
@if (\Illuminate\Support\Facades\Session::has('message'))
<script>
    toastr.success('{{ \Illuminate\Support\Facades\Session::get('message') }}');
</script>
@endif
<script>
    $(document).ready(function() {
        $('#table_id_events').DataTable()

    })
</script>
@endsection

@section('js')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.0/sweetalert.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
<!-- Add this to your layout file, before the Bootstrap JavaScript file -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
@endsection
