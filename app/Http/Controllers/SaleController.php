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

    // Set timezone to Pakistan (Asia/Karachi)
    $startOfDay = \Carbon\Carbon::now('Asia/Karachi')->startOfDay();
    $endOfDay = \Carbon\Carbon::now('Asia/Karachi')->endOfDay();

    // Fetch sales for today in PKT
    $sales = \App\Models\Sale::with(['vendor', 'items.batch.accessory', 'user'])
        ->whereBetween('sale_date', [$startOfDay, $endOfDay])
        ->orderByDesc('id')
        ->get();

    // Calculate totals
    $totalSellingPrice = $sales->sum('total_amount');

    $totalPaidPrice = $sales->sum(function ($sale) {
        if ($sale->vendor) {
            return (float) ($sale->pay_amount ?? 0);
        }
        return (float) $sale->total_amount; // Walk-in customers pay full
    });

    return view('sales.pos', compact(
        'vendors',
        'batches',
        'sales',
        'totalSellingPrice',
        'totalPaidPrice'
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






// public function checkout(Request $request)
// {
//     $data = $request->validate([
//         'vendor_id'        => 'nullable|exists:vendors,id',
//         'customer_name'    => 'nullable|string|max:255',
//         'customer_mobile'  => 'nullable|string|max:20',
//         'items'            => 'required|array|min:1',
//         'items.*.barcode'  => 'required|string',
//         'items.*.qty'      => 'required|integer|min:1',
//         'items.*.price'    => 'required|numeric|min:0', // keep if you allow manual price override
//         'items.*.accessory'=> 'nullable|string',        // not used server-side; optional
//         'pay_amount'       => 'nullable|numeric|min:0',
//         'cart_discount'    => 'nullable|numeric|min:0', // FLAT amount off the whole cart
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
//                 'total_amount'    => 0,     // set after we compute totals
//                 'discount_amount' => 0,     // set after we compute totals
//                 'user_id'         => auth()->id(),
//                 'status'          => 'pending',
//                 'approved_at'     => null,
//                 'approved_by'     => null,
//             ]);

//             $total = 0;

//             foreach ($data['items'] as $item) {
//                 // Lock the batch row to prevent race conditions
//                 /** @var \App\Models\AccessoryBatch|null $batch */
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

//                 // Which price should be authoritative?
//                 // If you allow manual override in UI, use posted price; otherwise use batch price.
//                 $unitPrice = (float) $item['price']; // or: (float) $batch->selling_price;

//                 $lineSubtotal = $unitPrice * $qty;
//                 $total += $lineSubtotal;

//                 // Persist line
//                 \App\Models\SaleItem::create([
//                     'sale_id'            => $sale->id,
//                     'accessory_batch_id' => $batch->id,
//                     'accessory_id'       => $batch->accessory_id,
//                     'quantity'           => $qty,
//                     'price_per_unit'     => $unitPrice,
//                     'subtotal'           => $lineSubtotal,
//                     'user_id'            => auth()->id(),
//                 ]);

//                 // Decrement stock
//                 $batch->decrement('qty_remaining', $qty);
//             }

//             // Apply FLAT cart discount
//             $cartDiscount = (float) ($data['cart_discount'] ?? 0);
//             if ($cartDiscount < 0) $cartDiscount = 0;
//             if ($cartDiscount > $total) $cartDiscount = $total;

//             $sale->total_amount    = $total - $cartDiscount;
//             $sale->discount_amount = $cartDiscount;
//             $sale->save();

//             // ACCOUNTS ENTRIES (vendor credit/debit)
//             if (!empty($data['vendor_id'])) {
//                 // Vendor owes the net sale amount (Debit)
//                 \App\Models\Accounts::create([
//                     'vendor_id'   => $data['vendor_id'],
//                     'Debit'       => $sale->total_amount,
//                     'Credit'      => 0,
//                     'description' => "Sale Invoice #{$sale->id}",
//                     'created_by'  => auth()->id(),
//                 ]);

//                 // If they pay now, record payment (Credit)
//                 if (!empty($data['pay_amount']) && (float)$data['pay_amount'] > 0) {
//                     \App\Models\Accounts::create([
//                         'vendor_id'   => $data['vendor_id'],
//                         'Debit'       => 0,
//                         'Credit'      => (float)$data['pay_amount'],
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


public function checkout(Request $request)
{
    $data = $request->validate([
        'vendor_id'        => 'nullable|exists:vendors,id',
        'customer_name'    => 'nullable|string|max:255',
        'customer_mobile'  => 'nullable|string|max:20',
        'items'            => 'required|array|min:1',
        'items.*.barcode'  => 'required|string',
        'items.*.qty'      => 'required|integer|min:1',
        'items.*.price'    => 'required|numeric|min:0',
        'items.*.accessory'=> 'nullable|string',
        'pay_amount'       => 'nullable|numeric|min:0',
        'cart_discount'    => 'nullable|numeric|min:0',
    ]);

    // Normalize basic customer info (walk-in fallback)
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
        $sale = \DB::transaction(function () use ($data, $customerName, $customerMobile) {

            // Create sale shell
            $sale = \App\Models\Sale::create([
                'vendor_id'       => $data['vendor_id'] ?? null,
                'customer_name'   => $customerName,
                'customer_mobile' => $customerMobile,
                'sale_date'       => now(),
                'total_amount'    => 0,
                'discount_amount' => 0,
                'pay_amount'      => 0, // new field
                'user_id'         => auth()->id(),
                'status'          => 'pending',
                'approved_at'     => null,
                'approved_by'     => null,
            ]);

            $total = 0;

            foreach ($data['items'] as $item) {
                $batch = \App\Models\AccessoryBatch::where('barcode', $item['barcode'])
                    ->lockForUpdate()
                    ->first();

                if (!$batch) {
                    throw new \Exception('Batch not found for barcode ' . $item['barcode']);
                }
                if ($batch->qty_remaining < $item['qty']) {
                    throw new \Exception('Insufficient stock for batch ' . $item['barcode'] .
                                         '. Remaining: ' . $batch->qty_remaining);
                }

                $qty = (int) $item['qty'];
                $unitPrice = (float) $item['price']; // or $batch->selling_price

                $lineSubtotal = $unitPrice * $qty;
                $total += $lineSubtotal;

                \App\Models\SaleItem::create([
                    'sale_id'            => $sale->id,
                    'accessory_batch_id' => $batch->id,
                    'accessory_id'       => $batch->accessory_id,
                    'quantity'           => $qty,
                    'price_per_unit'     => $unitPrice,
                    'subtotal'           => $lineSubtotal,
                    'user_id'            => auth()->id(),
                ]);

                $batch->decrement('qty_remaining', $qty);
            }

            // Apply FLAT cart discount
            $cartDiscount = (float) ($data['cart_discount'] ?? 0);
            if ($cartDiscount < 0) $cartDiscount = 0;
            if ($cartDiscount > $total) $cartDiscount = $total;

            $netTotal = $total - $cartDiscount;

            // Determine pay_amount
            $rawPay = (float) ($data['pay_amount'] ?? 0);
            if (empty($data['vendor_id'])) {
                $pay = 0.0;
            } else {
                $pay = max(0.0, min($rawPay, $netTotal));
            }

            // Save totals + pay_amount
            $sale->total_amount    = $netTotal;
            $sale->discount_amount = $cartDiscount;
            $sale->pay_amount      = $pay;
            $sale->save();

            // ACCOUNTS ENTRIES
            if (!empty($data['vendor_id'])) {
                \App\Models\Accounts::create([
                    'vendor_id'   => $data['vendor_id'],
                    'Debit'       => $sale->total_amount,
                    'Credit'      => 0,
                    'description' => "Sale Invoice #{$sale->id}",
                    'created_by'  => auth()->id(),
                ]);

                if ($pay > 0) {
                    \App\Models\Accounts::create([
                        'vendor_id'   => $data['vendor_id'],
                        'Debit'       => 0,
                        'Credit'      => $pay,
                        'description' => "Payment for Invoice #{$sale->id}",
                        'created_by'  => auth()->id(),
                    ]);
                }
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
        'return_qty' => 'required|array',
        'return_qty.*' => 'nullable|integer|min:0',
    ]);

    \DB::beginTransaction();
    try {
        $totalReturnValue = 0;
        $hasReturn = false;

        // 1. Check if any item is selected for return
        foreach ($data['return_qty'] as $sale_item_id => $return_qty) {
            if ($return_qty && $return_qty > 0) {
                $hasReturn = true;
                break;
            }
        }

        if (!$hasReturn) {
            return response()->json(['success' => false, 'message' => 'No items selected for return.'], 422);
        }

        // 2. Create the SaleReturn record
        $salesReturn = \App\Models\SaleReturn::create([
            'sale_id' => $sale->id,
            'user_id' => auth()->id(),
            'reason'  => $request->input('reason', null),
        ]);

        // 3. Log returned items and update stock
        foreach ($data['return_qty'] as $sale_item_id => $return_qty) {
            if (!$return_qty || $return_qty < 1) continue;

            $saleItem = \App\Models\SaleItem::find($sale_item_id);
            if (!$saleItem) throw new \Exception('Sale item not found: ' . $sale_item_id);

            if ($return_qty > $saleItem->quantity) {
                throw new \Exception('Return quantity exceeds sold quantity for: ' . ($saleItem->batch->accessory->name ?? 'Unknown'));
            }
            \Log::info('sale_return_id', ['data' => $salesReturn->id]);
           
            // Log the return (make sure column name matches migration!)
            \App\Models\SaleReturnItems::create([
                'sale_return_id' => $salesReturn->id, // This is required!
                'sale_item_id'   => $saleItem->id,
                'quantity'       => $return_qty,
                'price_per_unit' => $saleItem->price_per_unit,
            ]);

            // Update sale_items
            $saleItem->quantity -= $return_qty;
            $saleItem->subtotal = $saleItem->quantity * $saleItem->price_per_unit;
            $saleItem->save();

            // Update batch stock
            $batch = $saleItem->batch;
            $batch->qty_remaining += $return_qty;
            $batch->save();

            $totalReturnValue += $return_qty * $saleItem->price_per_unit;
        }

        // 4. Update sale total
        $sale->total_amount -= $totalReturnValue;
        if ($sale->total_amount < 0) $sale->total_amount = 0;
        $sale->save();

        \DB::commit();
        return response()->json(['success' => true]);
    } catch (\Exception $e) {
        \DB::rollBack();
        \Log::info('Sales Return Debug', ['data' => $e]);
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
