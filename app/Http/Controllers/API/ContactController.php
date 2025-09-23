<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\ResponseTrait;
use App\Models\Contact;
use App\Mail\ContactUsMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ContactController extends Controller
{
    use ResponseTrait;

    public function store(Request $request)
    {
        try {
            $rules = [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:191'],
                'subject' => ['required', 'string', 'max:255'],
                'message' => ['required', 'string', 'max:5000'],
            ];
            
            $request->validate($rules);
            
            // Create contact record
            $contact = Contact::create($request->only(['name', 'email', 'subject', 'message']));
            
            // Queue email sending for better response time (MAJOR PERFORMANCE BOOST)
            dispatch(function() use ($contact) {
                Mail::to(env('ADMIN_EMAIL'))->send(new ContactUsMail($contact));
            })->afterResponse();
            
            // Return minimal response data
            return $this->sendResponse([
                'name' => $contact->name,
                'email' => $contact->email,
                'subject' => $contact->subject,
            ], 'Your contact request has been submitted successfully!', 201);

        } catch (ValidationException $e) {
            $errors = $e->errors();
            
            // Get the first error message for each field
            $firstErrorMessages = collect($errors)->map(fn($messages) => $messages[0])->implode(', ');
            return $this->sendError($firstErrorMessages, [], 422);

        } catch (\Exception $e) {
            return $this->sendError('Error during contact submission '. $e->getMessage(), [], 500);
        }
    }

}
