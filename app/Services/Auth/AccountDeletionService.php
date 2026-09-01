<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AccountDeletionService
{
    /**
     * Submit an account deletion request for the authenticated user.
     * Idempotent — calling it again is a no-op if already requested.
     */
    public function requestDeletion(User $user): void
    {
        if ($user->deletion_requested_at === null) {
            $user->update(['deletion_requested_at' => Carbon::now()]);
        }
    }

    /**
     * Cancel a pending deletion request (user changed their mind).
     */
    public function cancelDeletionRequest(User $user): void
    {
        $user->update(['deletion_requested_at' => null]);
    }

    /**
     * Admin: list users with pending deletion requests, paginated.
     */
    public function listPendingDeletions(): LengthAwarePaginator
    {
        return User::withTrashed()
            ->whereNotNull('deletion_requested_at')
            ->whereNull('deleted_at')
            ->with('barangay:id,name')
            ->orderBy('deletion_requested_at')
            ->paginate(15);
    }

    /**
     * Admin: process a deletion request.
     * - Soft-deletes the user
     * - Anonymises PII fields (name, email, mobile, photo)
     * - Revokes all tokens
     * - Removes profile photo and verification documents from storage
     *
     * In compliance with RA 10173 (Data Privacy Act of the Philippines).
     * Booking records are kept but the user's PII fields are nulled.
     */
    public function processDeletion(User $user): void
    {
        DB::transaction(function () use ($user): void {
            // Revoke all tokens first
            $user->tokens()->delete();

            // Remove stored files
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }

            if ($user->workerProfile) {
                $documents = $user->workerProfile->verificationDocuments;
                foreach ($documents as $doc) {
                    Storage::disk('public')->delete($doc->file_path);
                }
                // Anonymise the worker profile
                $user->workerProfile->update(['bio' => null]);
            }

            // Anonymise PII
            $anonymousId = 'deleted_' . $user->id;
            $user->update([
                'name'                    => 'Deleted User',
                'email'                   => "{$anonymousId}@deleted.hanapbuhay",
                'mobile_number'           => null,
                'profile_photo_path'      => null,
                'google_id'               => null,
                'password'                => null,
                'deletion_requested_at'   => null,
                'is_active'               => false,
            ]);

            // Soft-delete
            $user->delete();
        });
    }
}
