@extends('user_navbar')
@section('content')

<div class="app-content content">
    <div class="content-overlay"></div>
    <div class="content-wrapper">
        <div class="content-header row">
        </div>
        <div class="content-body">
            @if (session('success'))
            <div class="alert alert-success" id="successMessage">
                {{ session('success') }}
            </div>
            @endif

            @if (session('danger'))
            <div class="alert alert-danger" id="dangerMessage" style="color: red;">
                {{ session('danger') }}
            </div>
            @endif



            <div class="col-xxl-12 col-xl-12 col-lg-12 col-md-12 col-12 latest-update-tracking mt-1 ">
                <div class="card ">
                    <div class="card-header latest-update-heading d-flex justify-content-between">
                        <h4 class="latest-update-heading-title text-bold-500">Not Approved Sales</h4>
                        <div>
                            <h4>Total Selling Price (Not Approved): Rs. {{ number_format($totalSellingPrice, 2) }}</h4>
                            <h4>Total Paid Price (Not Approved): Rs. {{ number_format($totalPaidPrice, 2) }}</h4>
                        </div>

                    </div>
                    <div class="table-responsive">
                       <table class="table table-striped table-bordered zero-configuration">
                        <thead>
                            <tr>
                                <th>Sale #</th>
                                <th>Date</th>
                                <th>Customer/Vendor</th>
                                <th>Mobile</th>
                                <th>Total</th>
                                <th>Paid Amount</th>
                                <th>Items</th>
                                <th>Added By</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                      <tbody>
                        @forelse($sales as $sale)
                        <tr>
                            <td>{{ $sale->id }}</td>
                            <td>{{ \Carbon\Carbon::parse($sale->sale_date)->format('d M Y, H:i') }}</td>
                            <td>
                                @if($sale->vendor)
                                <strong>Vendor:</strong> {{ $sale->vendor->name }}
                                @elseif($sale->customer_name)
                                <strong>Customer:</strong> {{ $sale->customer_name }}
                                @else
                                Walk-in
                                @endif
                            </td>
                            <td>
                                @if($sale->vendor)
                                {{ $sale->vendor->mobile_no }}
                                @else
                                {{ $sale->customer_mobile }}
                                @endif
                            </td>
                            <td>
                                @php
                                $subtotal = $sale->items->sum('subtotal');
                                $discount = (float) ($sale->discount_amount ?? 0);
                                @endphp
                                <strong>Rs. {{ number_format($sale->total_amount, 2) }}</strong>
                                @if($discount > 0)
                                <div style="font-size:12px;color:#666; line-height:1.2; margin-top:4px;">
                                    <div>Subtotal: Rs. {{ number_format($subtotal, 2) }}</div>
                                    <div>Discount: - Rs. {{ number_format($discount, 2) }}</div>
                                </div>
                                @endif
                            </td>
                            <td>
                                {{-- Pay Amount Column --}}
                                @if($sale->vendor)
                                Rs. {{ number_format($sale->pay_amount ?? 0, 2) }}
                                @else
                                Rs. {{ number_format($sale->total_amount, 2) }}
                                @endif
                            </td>
                            <td>
                                <ul style="margin:0; padding-left: 1rem;">
                                    @foreach($sale->items as $item)
                                    <li>
                                        {{ $item->batch->accessory->name ?? '-' }} x{{ $item->quantity }}
                                        ({{ number_format($item->price_per_unit, 2) }} each
                                        @if(($sale->discount_amount ?? 0) > 0)
                                        — before discount
                                        @endif
                                        )
                                    </li>
                                    @endforeach
                                </ul>
                            </td>
                            <td>{{ $sale->user->name ?? '-' }}</td>
                            <td>
                                <span class="badge bg-warning text-dark">Pending</span>
                            </td>
                            <td>
                                <form action="{{ route('sales.approve', $sale->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm">
                                        Approve
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center">No pending sales found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                    </table>

                        
                    </div>
                </div>
            </div>


        </div>
    </div>
</div>


@endsection