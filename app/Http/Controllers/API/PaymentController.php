<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\User;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Customer;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Barryvdh\DomPDF\Facade\Pdf as PDF;

class PaymentController extends Controller
{
    use ResponseTrait;

    public function processPayment(Request $request, $purchaseId)
    {
        // Quick validation first (before database queries)
        $validator = Validator::make($request->all(), [
            'payment_method_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed: ' . $validator->errors()->first(), [], 422);
        }

        $validated = $validator->validated();

        // Get authenticated user (already in memory from middleware)
        $user = Auth::user();

        if (!$user) {
            return $this->sendError('User not authenticated', [], 401);
        }

        // Optimized single query with specific columns and relationship
        $purchase = Purchase::select([
            'id', 'user_id', 'product_id', 'payment_amount', 'payment_status', 
            'order_status', 'payment_id', 'payment_type', 'total_pieces'
        ])
        ->with(['product' => function($query) {
            $query->select('id', 'code', 'name');
        }])
        ->where('id', $purchaseId)
        ->where('user_id', $user->id) // Combined authorization check
        ->first();

        if (!$purchase) {
            return $this->sendError('Purchase not found or unauthorized', [], 404);
        }

        $product = $purchase->product;
        if (!$product) {
            return $this->sendError('Product not found', [], 404);
        }

        // Validate amount before starting transaction
        $amount = $purchase->payment_amount;
        if ($amount <= 0) {
            return $this->sendError('Invalid payment amount', [], 400);
        }

        // Check if already paid (quick check before Stripe call)
        if ($purchase->payment_status === 'paid') {
            return $this->sendError('Payment already completed',[
                'purchase_id' => $purchase->id,
                'payment_amount' => number_format($amount, 2),
                'payment_status' => 'paid',
                'stripe_payment_intent_id' => $purchase->payment_id,
            ], 400);
        }

        // Set Stripe API key once
        Stripe::setApiKey(config('services.stripe.secret'));

        // Start transaction only when ready to process
        DB::beginTransaction();

        try {
            // Get or create Stripe customer (optimized with caching)
            $customer = $this->findOrCreateStripeCustomer($user);

            // Create payment intent
            $paymentIntent = PaymentIntent::create([
                'amount' => intval($amount * 100), // Convert to cents
                'currency' => 'usd',
                'payment_method' => $validated['payment_method_id'],
                'customer' => $customer->id,
                'confirmation_method' => 'manual',
                'confirm' => true,
                'return_url' => config('app.frontend_url', config('app.url')),
                'description' => "Purchase #{$purchase->id} - {$product->name}",
                'metadata' => [
                    'user_id' => $user->id,
                    'purchase_id' => $purchase->id,
                    'product_id' => $purchase->product_id,
                    'payment_type' => $purchase->payment_type,
                    'product_code' => $product->code ?? 'N/A',
                ]
            ]);

            if ($paymentIntent->status === 'succeeded') {
                // Quick duplicate check using DB query builder
                $existingPayment = DB::table('purchases')
                    ->where('payment_id', $paymentIntent->id)
                    ->where('id', '!=', $purchase->id)
                    ->exists();

                if ($existingPayment) {
                    DB::rollback();
                    return $this->sendResponse([
                        'purchase_id' => $purchase->id,
                        'payment_status' => 'paid',
                        'transaction_id' => $paymentIntent->id,
                    ], 'Payment already processed');
                }

                // Update using query builder for speed
                DB::table('purchases')
                    ->where('id', $purchase->id)
                    ->update([
                        'payment_status' => 'paid',
                        'payment_id' => $paymentIntent->id,
                        'updated_at' => now()
                    ]);

                DB::commit();

                // Return response without reloading relationships
                return $this->sendResponse([
                        'purchase_id' => $purchase->id,
                        'product_code' => $product->code ?? 'N/A',
                        'product_name' => $product->name,
                        'payment_amount' => number_format($amount, 2),
                        'payment_status' => 'paid',
                        'stripe_payment_intent_id' => $paymentIntent->id,
                        'paid_at' => now()->format('Y-m-d H:i:s')
                ], 'Payment processed successfully!');

            } elseif ($paymentIntent->status === 'requires_action') {
                DB::rollback();
                return $this->sendResponse([
                    'requires_action' => true,
                    'payment_intent_id' => $paymentIntent->id,
                    'client_secret' => $paymentIntent->client_secret,
                    'amount' => '$' . number_format($amount, 2)
                ], 'Payment requires additional authentication');

            } else {
                DB::rollback();
                return $this->sendError('Payment failed. Status: ' . $paymentIntent->status, [
                    'payment_intent_id' => $paymentIntent->id,
                    'status' => $paymentIntent->status,
                ], 400);
            }

        } catch (CardException $e) {
            DB::rollback();
            Log::error("Card declined", [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'purchase_id' => $purchaseId,
            ]);
            return $this->sendError('Card was declined: ' . $e->getError()->message, [], 400);

        } catch (InvalidRequestException $e) {
            DB::rollback();
            Log::error("Invalid Stripe request", [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'purchase_id' => $purchaseId,
            ]);
            return $this->sendError('Invalid payment request: ' . $e->getMessage(), [], 400);

        } catch (AuthenticationException $e) {
            DB::rollback();
            Log::error("Stripe authentication failed: " . $e->getMessage());
            return $this->sendError('Payment service authentication failed', [], 500);

        } catch (ApiConnectionException $e) {
            DB::rollback();
            Log::error("Stripe connection failed: " . $e->getMessage());
            return $this->sendError('Payment service temporarily unavailable', [], 503);

        } catch (ApiErrorException $e) {
            DB::rollback();
            Log::error("Stripe API error: " . $e->getMessage(), [
                'purchase_id' => $purchaseId,
                'user_id' => $user->id,
            ]);
            return $this->sendError('Payment processing error', [], 500);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Payment processing error", [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'purchase_id' => $purchaseId,
            ]);
            return $this->sendError('An unexpected error occurred: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Find or create Stripe customer (optimized)
     */
    private function findOrCreateStripeCustomer(User $user)
    {
        try {
            // Try to find existing customer
            $customers = Customer::all([
                'email' => $user->email,
                'limit' => 1
            ]);

            if (count($customers->data) > 0) {
                return $customers->data[0];
            }
        } catch (\Exception $e) {
            Log::warning("Could not search for existing Stripe customer: " . $e->getMessage());
        }

        // Create new customer
        return Customer::create([
            'email' => $user->email,
            'name' => $user->name ?? 'Customer',
            'metadata' => [
                'user_id' => $user->id,
            ],
        ]);
    }

    /**
     * Download invoice for a purchase
     */
    public function downloadInvoice($purchaseId)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->sendError('User not authenticated', [], 401);
            }

            // Get purchase with ALL details
            $purchase = Purchase::where('id', $purchaseId)
                ->where('user_id', $user->id)
                ->first();

            if (!$purchase) {
                return $this->sendError('Purchase not found or unauthorized', [], 404);
            }

            if ($purchase->payment_status !== 'paid') {
                return $this->sendError('Invoice not available. Payment not completed.', [], 400);
            }

            $product = $purchase->product;
            if (!$product) {
                return $this->sendError('Product not found', [], 404);
            }

            // Get logo as base64
            $logoPath = public_path('images/logo.png');
            $logoBase64 = '';
            $logoMimeType = '';
            
            if (file_exists($logoPath)) {
                $logoBase64 = base64_encode(file_get_contents($logoPath));
                $logoMimeType = mime_content_type($logoPath);
            }

            // Prepare data for PDF
            $data = [
                'purchase' => $purchase,
                'product' => $product,
                'user' => $user,
                'invoice_number' => 'INV-' . str_pad($purchase->id, 6, '0', STR_PAD_LEFT),
                'generated_at' => now()->format('d M Y, g:i A'),
                'logo' => $logoBase64 ? "data:{$logoMimeType};base64,{$logoBase64}" : null,
            ];

            // Load PDF view
            $pdf = PDF::loadView('pdf.invoice', $data);
            $pdf->setPaper('A4', 'portrait');
            
            $filename = 'invoice-' . $data['invoice_number'] . '-' . now()->format('Y-m-d') . '.pdf';
            
            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error("Invoice download error: " . $e->getMessage(), [
                'user_id' => $user->id ?? null,
                'purchase_id' => $purchaseId,
            ]);
            return $this->sendError('Error generating invoice: ' . $e->getMessage(), [], 500);
        }
    }


}
