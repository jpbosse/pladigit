<?php

namespace App\Http\Controllers;

use App\Mail\ContactRequestMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'organization' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'plan' => ['nullable', 'string', 'in:communautaire,partenaire,'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        Mail::to('contact@pladigit.fr')->send(new ContactRequestMail(
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            organization: $data['organization'],
            email: $data['email'],
            plan: $data['plan'] ?? '',
            message: $data['message'] ?? '',
        ));

        return redirect('/')->with('contact_success',
            "Merci {$data['first_name']} ! Nous vous recontacterons dans les 48h."
        );
    }
}
