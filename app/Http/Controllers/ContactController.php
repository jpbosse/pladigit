<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function send(Request $request)
    {
        $request->validate([
            'first_name'   => ['required', 'string', 'max:100'],
            'last_name'    => ['required', 'string', 'max:100'],
            'organization' => ['required', 'string', 'max:255'],
            'email'        => ['required', 'email'],
            'plan'         => ['nullable', 'string'],
            'message'      => ['nullable', 'string', 'max:2000'],
        ]);

        // Log la demande en attendant le SMTP production
        \Log::info('Demande de démo', $request->only([
            'first_name', 'last_name', 'organization', 'email', 'plan', 'message'
        ]));

        return redirect('/')->with('contact_success',
            "Merci {$request->first_name} ! Nous vous recontacterons dans les 48h."
        );
    }
}
