<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class ScalevWebhookController extends Controller
{
    /**
     * Handle webhook from ScaleV: when status changes from not paid to paid,
     * create (or update) a user with role 'member' and random password.
     */
    public function handle(Request $request)
    {
        // Optional signature verification (HMAC SHA256 of raw body with secret)
        $secret = env('SCALEV_WEBHOOK_SECRET');
        if (!empty($secret)) {
            $signature = $request->header('X-ScaleV-Signature');
            $computed = hash_hmac('sha256', $request->getContent(), $secret);
            if (!$signature || !hash_equals($computed, $signature)) {
                return response()->json(['ok' => false, 'error' => 'Invalid signature'], 401);
            }
        }

        // Basic payload validation
        $validator = Validator::make($request->all(), [
            'status_from' => 'required|string',
            'status_to'   => 'required|string',
            'name'        => 'required|string|max:255',
            'email'       => 'required|email',
            'phone'       => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'errors' => $validator->errors()], 422);
        }

        $from = strtolower($request->input('status_from'));
        $to   = strtolower($request->input('status_to'));

        // Accept various not-paid labels
        $notPaidLabels = ['not_paid', 'unpaid', 'pending'];
        if (!in_array($from, $notPaidLabels, true) || $to !== 'paid') {
            return response()->json(['ok' => true, 'result' => 'ignored', 'reason' => 'no-paid-transition']);
        }

        // Create or update user
        $email = $request->input('email');
        $name  = $request->input('name');
        $phone = $request->input('phone');

        $user = User::where('email', $email)->first();
        $created = false;
        $plainPassword = null;

        if (!$user) {
            $created = true;
            $plainPassword = Str::random(12);
            $user = new User();
            $user->name = $name;
            $user->email = $email;
            $user->phone = $phone;
            $user->role = 'member';
            $user->password = Hash::make($plainPassword);
            $user->save();

            Log::info('ScaleV webhook created member', [
                'email' => $email,
                // Do NOT log plaintext password in production. Shown here for handoff purposes only.
            ]);
        } else {
            // Ensure role and profile data are up to date
            $user->name = $name;
            $user->phone = $phone;
            $user->role = 'member';
            $user->save();
        }

        // Optionally, you can email the password or a password-set link here.
        // Leaving out email sending to keep scope minimal.

        return response()->json([
            'ok' => true,
            'result' => $created ? 'created' : 'updated',
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            // Return temporary password only when newly created
            'temporary_password' => $created ? $plainPassword : null,
        ], $created ? 201 : 200);
    }
}

