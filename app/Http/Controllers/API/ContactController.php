<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\ResponseTrait;
use App\Models\Contact;
use App\Mail\ContactUsMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\ValidationException;

class ContactController extends Controller
{
    use ResponseTrait;

    public function index(Request $request)
    {
        try {
            $limit = $request->get('limit', 10);
            $page = $request->get('page', 1);
            $search = $request->get('search');

            // Create specific cache key
            $cacheKey = "contacts:list:page_{$page}:limit_{$limit}:search_" . md5($search ?? 'all');
            
            // Use Laravel Cache with PhpRedis
            $cachedResult = Cache::remember($cacheKey, 300, function () use ($limit, $page, $search) {
                $contactQuery = Contact::query();

                if ($search) {
                    $contactQuery->searchLike($search);
                }

                $contacts = $contactQuery
                    ->select(['id', 'name', 'email', 'phone', 'business_name', 'business_category', 'address', 'subject', 'message', 'created_at'])
                    ->orderBy('created_at', 'desc')
                    ->paginate($limit, ['*'], 'page', $page);

                if ($contacts->isEmpty()) {
                    return null;
                }

                $contacts->getCollection()->transform(function ($contact) {
                    return [
                        'id' => $contact->id,
                        'name' => $contact->name,
                        'email' => $contact->email,
                        'phone' => $contact->phone,
                        'business_name' => $contact->business_name,
                        'business_category' => $contact->business_category,
                        'address' => $contact->address,
                        'subject' => $contact->subject,
                        'message' => $contact->message,
                        'submitted_at' => $contact->created_at->format('d M Y, g:i A'),
                    ];
                });

                return [
                    'contacts' => $contacts->items(),
                    'meta' => [
                        'limit' => $contacts->perPage(),
                        'page' => $contacts->currentPage(),
                        'total' => $contacts->total(), 
                        'last_page' => $contacts->lastPage(),
                    ],
                ];
            });

            if (!$cachedResult) {
                return $this->sendError('No contacts found.', [], 404);
            }

            return $this->sendResponse($cachedResult, 'Contacts retrieved successfully');

        } catch (\Exception $e) {
            return $this->sendError('An unexpected error occurred while fetching contacts. ' . $e->getMessage(), [], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $rules = [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'max:191'],
                'phone' => ['required', 'string', 'max:20'],
                'business_name' => ['nullable', 'string', 'max:100'],
                'business_category' => ['nullable', 'string', 'max:100'],
                'address' => ['required', 'string', 'max:255'],
                'subject' => ['required', 'string', 'max:255'],
                'message' => ['required', 'string'],
            ];

            $request->validate($rules);

            $contact = Contact::create($request->only(['name', 'email', 'phone', 'business_name', 'business_category', 'address', 'subject', 'message']));

            // Clear contact list cache - simplified approach
            $this->clearContactsCache();

            $adminEmail = config('mail.admin_email');
            if ($adminEmail && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                try {
                    Mail::to($adminEmail)->send(new ContactUsMail($contact));
                    Log::info('Contact email sent successfully to: ' . $adminEmail);
                } catch (\Exception $e) {
                    Log::error('Failed to send contact email: ' . $e->getMessage());
                }
            } else {
                Log::warning('ADMIN_EMAIL not set or invalid: ' . ($adminEmail ?? 'null'));
            }

            return $this->sendResponse([
                'name' => $contact->name,
                'email' => $contact->email,
                'subject' => $contact->subject,
            ], 'Your contact request has been submitted successfully!', 201);

        } catch (ValidationException $e) {
            $errors = $e->errors();
            $firstErrorMessages = collect($errors)->map(fn($messages) => $messages[0])->implode(', ');
            return $this->sendError($firstErrorMessages, [], 422);

        } catch (\Exception $e) {
            return $this->sendError('Error during contact submission '. $e->getMessage(), [], 500);
        }
    }

    private function clearContactsCache()
    {
        try {
            Cache::flush(); 
        } catch (\Exception $e) {
            Log::warning('Failed to clear contacts cache: ' . $e->getMessage());
        }
    }


}
