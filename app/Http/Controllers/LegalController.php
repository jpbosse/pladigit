<?php

namespace App\Http\Controllers;

class LegalController extends Controller
{
    public function mentions(): \Illuminate\View\View
    {
        return view('legal.mentions');
    }

    public function confidentialite(): \Illuminate\View\View
    {
        return view('legal.confidentialite');
    }
}
