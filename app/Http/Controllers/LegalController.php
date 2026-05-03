<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class LegalController extends Controller
{
    public function mentions(): View
    {
        return view('legal.mentions');
    }

    public function confidentialite(): View
    {
        return view('legal.confidentialite');
    }
}
