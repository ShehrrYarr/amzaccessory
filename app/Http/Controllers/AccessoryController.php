<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Accessory;
use App\Models\company;
use App\Models\group;
use App\Models\MasterPassword;



class AccessoryController extends Controller
{


    public function __construct()
    {
        $this->middleware('auth');
    }


    
    public function index()
{
    $companies = company::all();
    $groups = group::all();
    $accessories = Accessory::with(['group', 'company', 'user', 'batches'])->get();
    return view('accessories.index', compact('accessories','companies','groups'));
}

public function store(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'description' => 'nullable|string',
        'min_qty' => 'nullable|string',
        'group_id' => 'required|exists:groups,id',
        'company_id' => 'required|exists:companies,id',
        
    ]);

    $validated['user_id'] = auth()->id(); // Store the user who added it

    \App\Models\Accessory::create($validated);
    return redirect()->back()->with('success', 'Accessory Created Successfully.');
}



public function edit($id)
    {
        $filterId = Accessory::find($id);
        // dd($filterId);
        if (!$filterId) {

            return response()->json(['message' => 'Id not found'], 404);
        }

        return response()->json(['result' => $filterId]);

    }

    public function update(Request $request)
{
    $password = $request->input('password');
        $masterPassword = MasterPassword::first();

    $accessory = \App\Models\Accessory::findOrFail($request->id);
 if ($password === $masterPassword->update_password) {
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'description' => 'nullable|string',
        'min_qty' => 'nullable|string',
        'group_id' => 'required|exists:groups,id',
        'company_id' => 'required|exists:companies,id',
    ]);

    $accessory->update($validated);

    return redirect()->back()->with('success', 'Accessory Updated Successfully.');
 }
 else {
    return redirect()->back()->with('danger', 'Incorrect update password.');
 }
}

public function filter(Request $request)
{
    $companies = Company::all();
    $groups = Group::all();

    // Build query with optional filters
    $query = Accessory::with(['group', 'company', 'user', 'batches']);

    // Filter by group_id if present
    if ($request->filled('group_id')) {
        $query->where('group_id', $request->input('group_id'));
    }

    // Filter by company_id if present
    if ($request->filled('company_id')) {
        $query->where('company_id', $request->input('company_id'));
    }

    $accessories = $query->get();

    return view('accessories.filter', compact('accessories', 'companies', 'groups'));
}




}
