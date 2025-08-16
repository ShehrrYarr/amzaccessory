<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\vendor;
use App\Models\AccessoryBatch;
use App\Models\Accounts;
use Illuminate\Support\Facades\DB;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\CustomerInfo;
use App\Models\SaleReturnItems;
use App\Models\SaleReturn;
use App\Models\Bank;
use App\Models\SalePayment;
use Carbon\Carbon;


use Barryvdh\DomPDF\Facade\Pdf;

// function sendWhatsAppMessage($number, $message)
// {
//     // Remove all non-digits (spaces, +, -, etc.)
//     $number = preg_replace('/\D/', '', $number);

//     $url = "https://wa980.50015001.xyz/api/send";
//     $params = [
//         'number'       => $number,            // Must be in format: 92xxxxxxxxxx
//         'type'         => 'text',
//         'message'      => $message,
//         'instance_id'  => '6860FBD0A05BE',
//         'access_token' => '6860cd24517cb',
//     ];

//     $client = new \GuzzleHttp\Client();

//     try {
//         // Use GET, not POST, with query parameters
//         $response = $client->get($url, ['query' => $params, 'verify' => false]); // You can remove 'verify' => false once your SSL is fixed!
//         $body = $response->getBody()->getContents();
//         // Optionally decode and check for success
//         // $json = json_decode($body, true);

//         return $body;
//     } catch (\Exception $e) {
//         \Log::error('WhatsApp send error: '.$e->getMessage());
//         return false;
//     }
// }


function sendWhatsAppInvoice($number, $message, $media_url, $filename)
{
    $number = preg_replace('/\D/', '', $number); // Clean number, keep digits only
    $url = "https://wa980.50015001.xyz/api/send";
    $params = [
        'number'       => $number,
        'type'         => 'media',
        'message'      => $message,
        'media_url'    => $media_url,
        'filename'     => $filename,
        'instance_id'  => '6860FBD0A05BE',
        'access_token' => '6860cd24517cb',
    ];

    try {
        $client = new \GuzzleHttp\Client();
        $response = $client->get($url, ['query' => $params, 'verify' => false]);
        $body = $response->getBody()->getContents();
        return $body;
    } catch (\Exception $e) {
        \Log::error('WhatsApp send error: '.$e->getMessage());
        return false;
    }
}


class SaleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }


//     public function index()
// {
//     $vendors = vendor::all();
//     return view('sales.create', compact('vendors'));
// }

public function pos()
{
    $vendors = \App\Models\vendor::all();
    $batches = \App\Models\AccessoryBatch::with('accessory')
        ->where('qty_remaining', '>', 0)
        ->get();

    $startOfDay = \Carbon\Carbon::now('Asia/Karachi')->startOfDay();
    $endOfDay   = \Carbon\Carbon::now('Asia/Karachi')->endOfDay();

    // Eager-load payments + bank for each sale
    $sales = \App\Models\Sale::with([
            'vendor',
            'items.batch.accessory',
            'user',
            'payments.bank',
        ])
        ->whereBetween('sale_date', [$startOfDay, $endOfDay])
        ->orderByDesc('id')
        ->get();

    $banks = \App\Models\Bank::where('is_active', true)
        ->orderBy('name')
        ->get(['id','name','account_no']);

    // Existing totals
    $totalSellingPrice = $sales->sum('total_amount');
    $totalPaidPrice = $sales->sum(function ($sale) {
        if ($sale->vendor) {
            return (float) ($sale->pay_amount ?? 0);
        }
        return (float) $sale->total_amount; // Walk-in customers pay full
    });

    // NEW: payment totals
    $allPayments   = $sales->flatMap->payments; // Illuminate\Support\Collection of SalePayment
    $counterTotal  = (float) $allPayments->where('method', 'counter')->sum('amount');
    $bankTotal     = (float) $allPayments->where('method', 'bank')->sum('amount');

    // NEW: bank-wise breakdown (id => [name,total])
    $bankBreakdown = $allPayments
        ->where('method', 'bank')
        ->groupBy('bank_id')
        ->map(function ($group) {
            $first = $group->first();
            return [
                'name'  => optional($first->bank)->name ?? 'Unknown Bank',
                'total' => (float) $group->sum('amount'),
            ];
        });

        $start = Carbon::today();      // app timezone
    $end   = Carbon::tomorrow();

    // Refund value from items (positive number)
    $refundAgg = DB::table('sale_return_items as sri')
        ->join('sale_returns as sr', 'sr.id', '=', 'sri.sale_return_id')
        ->whereBetween('sr.created_at', [$start, $end])
        ->selectRaw('COALESCE(SUM(sri.quantity * sri.price_per_unit),0) as total_value')
        ->selectRaw('COUNT(*) as total_lines')
        ->selectRaw('COUNT(DISTINCT sr.id) as total_returns')
        ->first();

    // Cash/bank refunds actually paid out today (negative SalePayment rows)
    $refundPaidToday = (float) SalePayment::where('amount', '<', 0)
        ->whereBetween('paid_at', [$start, $end])
        ->sum('amount'); // negative number

    // Optional: breakdown by method (counter/bank)
    $refundPaidByMethod = SalePayment::select('method', DB::raw('SUM(-amount) as refunded')) // make positive
        ->where('amount', '<', 0)
        ->whereBetween('paid_at', [$start, $end])
        ->groupBy('method')
        ->pluck('refunded', 'method'); // ['counter' => 1234.50, 'bank' => 500.00]

    $todaysRefunds = [
        'value_from_items' => (float) $refundAgg->total_value,              // e.g. 2500.00
        'lines'            => (int)   $refundAgg->total_lines,              // e.g. 7 line items
        'returns'          => (int)   $refundAgg->total_returns,            // e.g. 3 return transactions
        'paid_out_total'   => abs($refundPaidToday),                        // make it positive for UI
        'paid_by_method'   => $refundPaidByMethod,                          // optional
        'net_effect'       => (float) $refundAgg->total_value - abs($refundPaidToday), // credit notes vs cash
    ];

    return view('sales.pos', compact(
        'vendors',
        'batches',
        'sales',
        'totalSellingPrice',
        'totalPaidPrice',
        'banks',
        'counterTotal',
        'bankTotal',
        'bankBreakdown',
        'todaysRefunds'
    ));
}


public function accessoryReport()
{
    
    return view('sales.report');
}

public function salesReport(Request $request)
{
    $start = $request->get('start');
    $end = $request->get('end');

    $sales = Sale::with(['vendor', 'items.batch'])
        ->whereDate('sale_date', '>=', $start)
        ->whereDate('sale_date', '<=', $end)
        ->get();

    $profit = 0;
    $salesData = [];
    foreach ($sales as $sale) {
        $itemsArr = [];
        foreach ($sale->items as $item) {
            $purchase_price = $item->batch->purchase_price ?? 0;
            $profit += ($item->price_per_unit - $purchase_price) * $item->quantity;
            $itemsArr[] = [
                'accessory' => $item->batch->accessory->name ?? '-',
                'barcode' => $item->batch->barcode,
                'quantity' => $item->quantity,
                'price_per_unit' => number_format($item->price_per_unit, 2),
                'subtotal' => number_format($item->subtotal, 2)
            ];
        }
        $salesData[] = [
            'id' => $sale->id,
            'sale_date' => $sale->sale_date,
            'sale_date_formatted' => \Carbon\Carbon::parse($sale->sale_date)->format('d M Y, H:i'),
            'customer_vendor' => $sale->vendor->name ?? $sale->customer_name ?? 'Walk-in',
            'total_amount' => number_format($sale->total_amount, 2),
            'items' => $itemsArr
        ];
    }

    return response()->json([
        'sales' => $salesData,
        'profit' => number_format($profit, 2)
    ]);
}






public function checkout(Request $request)
{
    $data = $request->validate([
        'vendor_id'         => 'nullable|exists:vendors,id',
        'customer_name'     => 'nullable|string|max:255',
        'customer_mobile'   => 'nullable|string|max:20',
        'items'             => 'required|array|min:1',
        'items.*.barcode'   => 'required|string',
        'items.*.qty'       => 'required|integer|min:1',
        'items.*.price'     => 'required|numeric|min:0',
        'items.*.accessory' => 'nullable|string',
        'cart_discount'     => 'nullable|numeric|min:0',

        // legacy single-payment hints (fallback if payments[] missing)
        'pay_amount'        => 'nullable|numeric|min:0',
        'payment_method'    => 'nullable|in:counter,bank',
        'bank_id'           => 'nullable|exists:banks,id',
        'reference_no'      => 'nullable|string|max:255',

        // preferred multi-payments
        'payments'                => 'sometimes|array',
        'payments.*.method'       => 'required_with:payments|in:counter,bank',
        'payments.*.bank_id'      => 'nullable|exists:banks,id',
        'payments.*.amount'       => 'required_with:payments|numeric|min:0.01',
        'payments.*.reference_no' => 'nullable|string|max:255',
    ]);

    // Walk‑in normalization
    $customerName   = $data['customer_name']   ?? null;
    $customerMobile = $data['customer_mobile'] ?? null;

    if (empty($data['vendor_id'])) {
        $customerName   = $customerName   ?: 'Walk In Customer';
        $customerMobile = $customerMobile ?: '00000000';

        \App\Models\CustomerInfo::firstOrCreate(
            ['mobile' => $customerMobile],
            ['name'   => $customerName]
        );
    }

    try {
        $sale = \DB::transaction(function () use ($data, $customerName, $customerMobile, $request) {

            // 1) Create Sale
            $sale = \App\Models\Sale::create([
                'vendor_id'       => $data['vendor_id'] ?? null,
                'customer_name'   => $customerName,
                'customer_mobile' => $customerMobile,
                'sale_date'       => now(),
                'total_amount'    => 0,
                'discount_amount' => 0,
                'pay_amount'      => 0,
                'user_id'         => auth()->id(),
                'status'          => 'pending',
                'approved_at'     => null,
                'approved_by'     => null,
            ]);

            // 2) Items & stock
            $gross = 0.0;
            foreach ($data['items'] as $item) {
                $batch = \App\Models\AccessoryBatch::where('barcode', $item['barcode'])
                    ->lockForUpdate()
                    ->first();

                if (!$batch) throw new \Exception('Batch not found for barcode ' . $item['barcode']);
                if ($batch->qty_remaining < $item['qty']) {
                    throw new \Exception('Insufficient stock for batch ' . $item['barcode'] .
                                         '. Remaining: ' . $batch->qty_remaining);
                }

                $qty       = (int) $item['qty'];
                $unitPrice = (float) $item['price']; // or (float)$batch->selling_price
                $line      = $unitPrice * $qty;
                $gross    += $line;

                \App\Models\SaleItem::create([
                    'sale_id'            => $sale->id,
                    'accessory_batch_id' => $batch->id,
                    'accessory_id'       => $batch->accessory_id,
                    'quantity'           => $qty,
                    'price_per_unit'     => $unitPrice,
                    'subtotal'           => $line,
                    'user_id'            => auth()->id(),
                ]);

                $batch->decrement('qty_remaining', $qty);
            }

            // 3) Totals
            $discount = max(0.0, (float) ($data['cart_discount'] ?? 0));
            if ($discount > $gross) $discount = $gross;
            $net = $gross - $discount;

            $sale->total_amount    = $net;
            $sale->discount_amount = $discount;
            $sale->save();

            // 4) Payments for BOTH flows
            $paymentsInput = $request->input('payments', []);
            $hasPayments   = is_array($paymentsInput) && count($paymentsInput) > 0;

            if (!empty($data['vendor_id'])) {
                // Vendor ledger: debit full net
                \App\Models\Accounts::create([
                    'vendor_id'   => $data['vendor_id'],
                    'Debit'       => $sale->total_amount,
                    'Credit'      => 0,
                    'description' => "Sale Invoice #{$sale->id}",
                    'created_by'  => auth()->id(),
                ]);

                $totalPaid = 0.0;

                if ($hasPayments) {
                    $soFar = 0.0;
                    foreach ($paymentsInput as $p) {
                        $method = $p['method'] ?? null;
                        $amount = isset($p['amount']) ? (float)$p['amount'] : 0.0;
                        if (!$method || $amount <= 0) continue;

                        $remaining = (float)$sale->total_amount - $soFar;
                        if ($remaining <= 0) break;
                        $use = min($amount, $remaining);

                        $bankId      = ($method === 'bank') ? ($p['bank_id'] ?? null) : null;
                        $referenceNo = $p['reference_no'] ?? null;

                        \App\Models\SalePayment::create([
                            'sale_id'      => $sale->id,
                            'method'       => $method,
                            'bank_id'      => $bankId,
                            'amount'       => $use,
                            'reference_no' => $referenceNo,
                            'processed_by' => auth()->id(),
                            'paid_at'      => now(),
                        ]);

                        $desc = "Payment for Invoice #{$sale->id} via " . strtoupper($method);
                        if ($bankId) {
                            $bankName = optional(\App\Models\Bank::find($bankId))->name;
                            if ($bankName) $desc .= " ({$bankName})";
                        }
                        if (!empty($referenceNo)) $desc .= " Ref: {$referenceNo}";

                        \App\Models\Accounts::create([
                            'vendor_id'   => $data['vendor_id'],
                            'Debit'       => 0,
                            'Credit'      => $use,
                            'description' => $desc,
                            'created_by'  => auth()->id(),
                        ]);

                        $soFar     += $use;
                        $totalPaid += $use;
                    }
                } else {
                    // Legacy single payment hint
                    $legacyPay = max(0.0, (float) ($data['pay_amount'] ?? 0));
                    $legacyPay = min($legacyPay, (float)$sale->total_amount);

                    if ($legacyPay > 0) {
                        $method = $data['payment_method'] ?? 'counter';
                        if (!in_array($method, ['counter', 'bank'], true)) $method = 'counter';

                        $bankId      = $method === 'bank' ? ($data['bank_id'] ?? null) : null;
                        $referenceNo = $method === 'bank' ? ($data['reference_no'] ?? null) : null;

                        \App\Models\SalePayment::create([
                            'sale_id'      => $sale->id,
                            'method'       => $method,
                            'bank_id'      => $bankId,
                            'amount'       => $legacyPay,
                            'reference_no' => $referenceNo,
                            'processed_by' => auth()->id(),
                            'paid_at'      => now(),
                        ]);

                        $desc = "Payment for Invoice #{$sale->id} via " . strtoupper($method);
                        if ($bankId) {
                            $bankName = optional(\App\Models\Bank::find($bankId))->name;
                            if ($bankName) $desc .= " ({$bankName})";
                        }
                        if (!empty($referenceNo)) $desc .= " Ref: {$referenceNo}";

                        \App\Models\Accounts::create([
                            'vendor_id'   => $data['vendor_id'],
                            'Debit'       => 0,
                            'Credit'      => $legacyPay,
                            'description' => $desc,
                            'created_by'  => auth()->id(),
                        ]);

                        $totalPaid = $legacyPay;
                    }
                }

                $sale->pay_amount = $totalPaid;
                $sale->save();

            } else {
                // Walk‑in: MUST record a full payment; if none provided, synthesize one
                if ($hasPayments) {
                    // Clamp total to net; ignore extra
                    $soFar = 0.0;
                    foreach ($paymentsInput as $p) {
                        $method = $p['method'] ?? 'counter';
                        $amount = isset($p['amount']) ? (float)$p['amount'] : 0.0;
                        if ($amount <= 0) continue;

                        $remaining = (float)$sale->total_amount - $soFar;
                        if ($remaining <= 0) break;
                        $use = min($amount, $remaining);

                        $bankId      = ($method === 'bank') ? ($p['bank_id'] ?? null) : null;
                        $referenceNo = $p['reference_no'] ?? null;

                        \App\Models\SalePayment::create([
                            'sale_id'      => $sale->id,
                            'method'       => in_array($method, ['counter','bank'], true) ? $method : 'counter',
                            'bank_id'      => $bankId,
                            'amount'       => $use,
                            'reference_no' => $referenceNo,
                            'processed_by' => auth()->id(),
                            'paid_at'      => now(),
                        ]);

                        $soFar += $use;
                    }
                } else {
                    // Fallback: single counter payment for full net (shouldn't happen with our JS)
                    \App\Models\SalePayment::create([
                        'sale_id'      => $sale->id,
                        'method'       => 'counter',
                        'bank_id'      => null,
                        'amount'       => $sale->total_amount,
                        'reference_no' => null,
                        'processed_by' => auth()->id(),
                        'paid_at'      => now(),
                    ]);
                }

                // Walk‑in considered fully paid
                $sale->pay_amount = $sale->total_amount;
                $sale->save();
                // No Accounts entries for walk‑ins
            }

            return $sale;
        });

        return response()->json([
            'success'        => true,
            'invoice_number' => $sale->id,
        ]);

    } catch (\Throwable $e) {
        \Log::error('Checkout Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
    }
}



// public function checkout(Request $request)
// {
//     $data = $request->validate([
//         'vendor_id'        => 'nullable|exists:vendors,id',
//         'customer_name'    => 'nullable|string|max:255',
//         'customer_mobile'  => 'nullable|string|max:20',
//         'items'            => 'required|array|min:1',
//         'items.*.barcode'  => 'required|string',
//         'items.*.qty'      => 'required|integer|min:1',
//         'items.*.price'    => 'required|numeric|min:0',
//         'items.*.accessory'=> 'nullable|string',
//         'pay_amount'       => 'nullable|numeric|min:0',
//         'cart_discount'    => 'nullable|numeric|min:0',
//     ]);

//     // Normalize basic customer info (walk-in fallback)
//     $customerName   = $data['customer_name']   ?? null;
//     $customerMobile = $data['customer_mobile'] ?? null;

//     if (empty($data['vendor_id'])) {
//         $customerName   = $customerName   ?: 'Walk In Customer';
//         $customerMobile = $customerMobile ?: '00000000';

//         \App\Models\CustomerInfo::firstOrCreate(
//             ['mobile' => $customerMobile],
//             ['name'   => $customerName]
//         );
//     }

//     try {
//         $sale = \DB::transaction(function () use ($data, $customerName, $customerMobile) {

//             // Create sale shell
//             $sale = \App\Models\Sale::create([
//                 'vendor_id'       => $data['vendor_id'] ?? null,
//                 'customer_name'   => $customerName,
//                 'customer_mobile' => $customerMobile,
//                 'sale_date'       => now(),
//                 'total_amount'    => 0,
//                 'discount_amount' => 0,
//                 'pay_amount'      => 0, // new field
//                 'user_id'         => auth()->id(),
//                 'status'          => 'pending',
//                 'approved_at'     => null,
//                 'approved_by'     => null,
//             ]);

//             $total = 0;

//             foreach ($data['items'] as $item) {
//                 $batch = \App\Models\AccessoryBatch::where('barcode', $item['barcode'])
//                     ->lockForUpdate()
//                     ->first();

//                 if (!$batch) {
//                     throw new \Exception('Batch not found for barcode ' . $item['barcode']);
//                 }
//                 if ($batch->qty_remaining < $item['qty']) {
//                     throw new \Exception('Insufficient stock for batch ' . $item['barcode'] .
//                                          '. Remaining: ' . $batch->qty_remaining);
//                 }

//                 $qty = (int) $item['qty'];
//                 $unitPrice = (float) $item['price']; // or $batch->selling_price

//                 $lineSubtotal = $unitPrice * $qty;
//                 $total += $lineSubtotal;

//                 \App\Models\SaleItem::create([
//                     'sale_id'            => $sale->id,
//                     'accessory_batch_id' => $batch->id,
//                     'accessory_id'       => $batch->accessory_id,
//                     'quantity'           => $qty,
//                     'price_per_unit'     => $unitPrice,
//                     'subtotal'           => $lineSubtotal,
//                     'user_id'            => auth()->id(),
//                 ]);

//                 $batch->decrement('qty_remaining', $qty);
//             }

//             // Apply FLAT cart discount
//             $cartDiscount = (float) ($data['cart_discount'] ?? 0);
//             if ($cartDiscount < 0) $cartDiscount = 0;
//             if ($cartDiscount > $total) $cartDiscount = $total;

//             $netTotal = $total - $cartDiscount;

//             // Determine pay_amount
//             $rawPay = (float) ($data['pay_amount'] ?? 0);
//             if (empty($data['vendor_id'])) {
//                 $pay = 0.0;
//             } else {
//                 $pay = max(0.0, min($rawPay, $netTotal));
//             }

//             // Save totals + pay_amount
//             $sale->total_amount    = $netTotal;
//             $sale->discount_amount = $cartDiscount;
//             $sale->pay_amount      = $pay;
//             $sale->save();

//             // ACCOUNTS ENTRIES
//             if (!empty($data['vendor_id'])) {
//                 \App\Models\Accounts::create([
//                     'vendor_id'   => $data['vendor_id'],
//                     'Debit'       => $sale->total_amount,
//                     'Credit'      => 0,
//                     'description' => "Sale Invoice #{$sale->id}",
//                     'created_by'  => auth()->id(),
//                 ]);

//                 if ($pay > 0) {
//                     \App\Models\Accounts::create([
//                         'vendor_id'   => $data['vendor_id'],
//                         'Debit'       => 0,
//                         'Credit'      => $pay,
//                         'description' => "Payment for Invoice #{$sale->id}",
//                         'created_by'  => auth()->id(),
//                     ]);
//                 }
//             }

//             return $sale;
//         });

//         return response()->json([
//             'success'        => true,
//             'invoice_number' => $sale->id,
//         ]);

//     } catch (\Throwable $e) {
//         \Log::error('Checkout Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
//         return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
//     }
// }











public function invoice($id)
{
    $sale = \App\Models\Sale::with(['items.batch.accessory', 'vendor', 'user'])->findOrFail($id);
    return view('sales.invoice', compact('sale'));
}


public function approve($id)
{
    // Only user with ID 1 can approve
    if (auth()->id() != 1) {
        return redirect()->back()->with('danger', 'You can not approve this sale');
    }

    $sale = Sale::findOrFail($id);

    // Only approve if not already approved
    if ($sale->status !== 'approved') {
        $sale->status = 'approved';
        $sale->approved_at = now();
        $sale->approved_by = auth()->id();
        $sale->save();

        return redirect()->back()->with('success', 'Sale approved!');
    }
    return redirect()->back()->with('danger', 'Sale already approved!');
}


// Show pending sales
public function pending()
{
    $sales = Sale::with('items', 'vendor', 'user')
        ->where('status', 'pending')
        ->orderBy('sale_date', 'desc')
        ->get();

    // Calculate totals for pending sales
    $totalSellingPrice = $sales->sum('total_amount');

    $totalPaidPrice = $sales->sum(function ($sale) {
        if ($sale->vendor) {
            return (float) ($sale->pay_amount ?? 0);
        }
        return (float) $sale->total_amount; // Walk-in customers pay full
    });

    return view('sales.pending', compact('sales', 'totalSellingPrice', 'totalPaidPrice'));
}


// Show approved sales
// public function approved()
// {
//     $sales = Sale::with('items', 'vendor', 'user')
//         ->where('status', 'approved')
//         ->orderBy('approved_at', 'desc')
//         ->get();

//     return view('sales.approved', compact('sales'));
// }

public function approved(Request $request)
{
    $query = Sale::with('items', 'vendor', 'user')
        ->where('status', 'approved');

    // Filter by date range if provided
    if ($request->filled('start_date') && $request->filled('end_date')) {
        $start = $request->input('start_date') . ' 00:00:00';
        $end = $request->input('end_date') . ' 23:59:59';
        $query->whereBetween('sale_date', [$start, $end]);
    }

    $sales = $query->orderBy('approved_at', 'desc')->get();

    // Total selling price
    $totalSellingPrice = $sales->sum('total_amount');

    // Total paid price
    $totalPaidPrice = $sales->sum(function ($sale) {
        if ($sale->vendor) {
            return (float) ($sale->pay_amount ?? 0);
        }
        return (float) $sale->total_amount; // Walk-in customers pay full
    });

    return view('sales.approved', compact('sales', 'totalSellingPrice', 'totalPaidPrice'));
}



// public function allSales()
// {
//     // You may want to paginate this if you have many sales
//     $sales = \App\Models\Sale::with(['vendor', 'items.batch.accessory', 'user'])->orderByDesc('id')->get();
//     return view('sales.all', compact('sales'));
// }

public function allSales(Request $request)
{
    $query = \App\Models\Sale::with(['vendor', 'items.batch.accessory', 'user']);

    // Filter by date range if provided
    if ($request->filled('start_date') && $request->filled('end_date')) {
        $start = $request->input('start_date') . ' 00:00:00';
        $end   = $request->input('end_date')   . ' 23:59:59';
        $query->whereBetween('sale_date', [$start, $end]);
    }

    $sales = $query->orderByDesc('id')->get();

    // Totals
    $totalSellingPrice = $sales->sum('total_amount');

    $totalPaidPrice = $sales->sum(function ($sale) {
        // Walk-in pays full; vendor pays whatever was recorded as pay_amount
        return $sale->vendor
            ? (float) ($sale->pay_amount ?? 0)
            : (float) $sale->total_amount;
    });

    return view('sales.all', compact('sales', 'totalSellingPrice', 'totalPaidPrice'));
}



public function ajaxSaleItems($saleId)
{
    $sale = \App\Models\Sale::with('items.batch.accessory')->findOrFail($saleId);

    // Prepare items for JSON
    $items = $sale->items->map(function($item) {
        return [
            'id' => $item->id,
            'accessory' => $item->batch->accessory->name ?? '-',
            'quantity' => $item->quantity,
        ];
    });

    return response()->json([
        'success' => true,
        'items' => $items,
    ]);
}

// public function processReturn(Request $request, Sale $sale)
// {
//     $data = $request->validate([
//         'return_qty' => 'required|array',
//         'return_qty.*' => 'nullable|integer|min:0',
//     ]);

//     \DB::beginTransaction();
//     try {
//         $totalReturnValue = 0;

//         foreach ($data['return_qty'] as $sale_item_id => $return_qty) {
//             if (!$return_qty) continue;

//             $saleItem = \App\Models\SaleItem::find($sale_item_id);
//             if (!$saleItem) throw new \Exception('Sale item not found: ' . $sale_item_id);

//             if ($return_qty > $saleItem->quantity) {
//                 throw new \Exception('Return quantity exceeds sold quantity for: ' . ($saleItem->batch->accessory->name ?? 'Unknown'));
//             }

//             // 1. Decrease quantity in sale_items
//             $saleItem->quantity -= $return_qty;
//             $saleItem->subtotal = $saleItem->quantity * $saleItem->price_per_unit; // Update subtotal!
//             $saleItem->save();

//             // 2. Increase qty_remaining in accessory_batch
//             $batch = $saleItem->batch;
//             $batch->qty_remaining += $return_qty;
//             $batch->save();

//             // 3. Keep track of return value
//             $totalReturnValue += $return_qty * $saleItem->price_per_unit;
//         }

//         // 4. Update the sale's total_amount
//         $sale->total_amount -= $totalReturnValue;
//         if ($sale->total_amount < 0) $sale->total_amount = 0; // Prevent negative totals
//         $sale->save();

//         \DB::commit();
//         return response()->json(['success' => true]);
//     } catch (\Exception $e) {
//         \DB::rollBack();
//         return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
//     }
// }



public function processReturn(Request $request, Sale $sale)
{
    $data = $request->validate([
        'return_qty'   => 'required|array',
        'return_qty.*' => 'nullable|integer|min:0',
        'reason'       => 'nullable|string|max:255',
    ]);

    \DB::beginTransaction();
    try {
        $hasReturn = collect($data['return_qty'] ?? [])->some(fn($q) => (int)$q > 0);
        if (!$hasReturn) {
            return response()->json(['success' => false, 'message' => 'No items selected for return.'], 422);
        }

        // Create return header
        $salesReturn = \App\Models\SaleReturn::create([
            'sale_id' => $sale->id,
            'user_id' => auth()->id(),
            'reason'  => $data['reason'] ?? null,
        ]);

        // Process items
        $totalReturnValue = 0.0;
        foreach ($data['return_qty'] as $sale_item_id => $return_qty) {
            $qty = (int) $return_qty;
            if ($qty < 1) continue;

            $saleItem = \App\Models\SaleItem::with('batch.accessory')->findOrFail($sale_item_id);

            if ($qty > $saleItem->quantity) {
                throw new \Exception('Return quantity exceeds sold quantity for: ' . ($saleItem->batch->accessory->name ?? 'Unknown item'));
            }

            // Log line
            \App\Models\SaleReturnItems::create([
                'sale_return_id' => $salesReturn->id,
                'sale_item_id'   => $saleItem->id,
                'quantity'       => $qty,
                'price_per_unit' => $saleItem->price_per_unit,
            ]);

            // Adjust sale item
            $saleItem->quantity -= $qty;
            $saleItem->subtotal  = round($saleItem->quantity * (float)$saleItem->price_per_unit, 2);
            $saleItem->save();

            // Return to stock
            $saleItem->batch->increment('qty_remaining', $qty);

            $totalReturnValue = round($totalReturnValue + ($qty * (float)$saleItem->price_per_unit), 2);
        }

        // Recompute sale total (clamped)
        $sale->total_amount = max(0, round((float)$sale->total_amount - $totalReturnValue, 2));
        $sale->save();

        // --- PAYMENTS / LEDGER IMPACT ---

        // Sum of payments already recorded (positive minus negative refunds)
        $paidSoFar = (float) $sale->payments()->sum('amount');

        if ($sale->vendor_id) {
            // VENDOR FLOW:
            // 1) Always create a CREDIT entry in Accounts (reduce receivable)
            //    Accounts.Debit/Credit are integers → drop paisa to rupees safely
            \App\Models\Accounts::create([
                'vendor_id'   => $sale->vendor_id,
                'Debit'       => 0,
                'Credit'      => (int) round($totalReturnValue), // rupees only
                'description' => "Return for Sale #{$sale->id} (SR# {$salesReturn->id})",
                'created_by'  => auth()->id(),
            ]);

            // 2) Optional cash refund only up to what’s actually paid
            //    (don’t let payments go negative)
            $refundToCash = min($totalReturnValue, max(0, $paidSoFar));
            if ($refundToCash > 0) {
                \App\Models\SalePayment::create([
                    'sale_id'      => $sale->id,
                    'method'       => 'counter',     // or mirror last payment method if you prefer
                    'bank_id'      => null,
                    'amount'       => -round($refundToCash, 2),  // negative = refund
                    'reference_no' => 'RETURN-' . $salesReturn->id,
                    'processed_by' => auth()->id(),
                    'paid_at'      => now(),
                ]);
            }

            // Refresh paid amount from ledger sum to avoid drift
            $sale->pay_amount = (float) $sale->payments()->sum('amount');
            if ($sale->pay_amount < 0) $sale->pay_amount = 0; // safety clamp
            $sale->save();

        } else {
            // WALK-IN FLOW:
            // Refund money out (counter by default) for the full returned value,
            // but never let pay_amount go negative.
            $refundToCash = min($totalReturnValue, max(0, $paidSoFar));

            if ($refundToCash > 0) {
                \App\Models\SalePayment::create([
                    'sale_id'      => $sale->id,
                    'method'       => 'counter',     // if you store payment method per item, mirror it here
                    'bank_id'      => null,
                    'amount'       => -round($refundToCash, 2),
                    'reference_no' => 'RETURN-' . $salesReturn->id,
                    'processed_by' => auth()->id(),
                    'paid_at'      => now(),
                ]);
            }

            $sale->pay_amount = (float) $sale->payments()->sum('amount');
            if ($sale->pay_amount < 0) $sale->pay_amount = 0;
            $sale->save();
        }

        \DB::commit();
        return response()->json(['success' => true]);

    } catch (\Throwable $e) {
        \DB::rollBack();
        \Log::error('Sales Return Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
    }
}


public function refundsPage()
{
    
    // Get all sale returns with their sale and return items
    $refunds = \App\Models\SaleReturn::with(['sale', 'items.saleItem.batch.accessory', 'user'])->latest()->get();
    // dd($refunds);

    return view('sales.refunds', compact('refunds'));
}






}
