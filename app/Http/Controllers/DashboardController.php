<?php

namespace App\Http\Controllers;

use App\Models\Item;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'users'    => \App\Models\User::count(),
            'items'    => Item::count(),
            'active'   => Item::where('is_active', true)->count(),
            'value'    => Item::sum(\DB::raw('price * stock')),
        ];

        $latestItems = Item::latest()->limit(5)->get();

        return view('dashboard.index', compact('stats', 'latestItems'));
    }
}
