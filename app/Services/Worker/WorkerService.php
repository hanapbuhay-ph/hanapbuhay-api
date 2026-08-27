<?php

namespace App\Services\Worker;

use App\Exceptions\BusinessRuleException;
use App\Http\Requests\Worker\SubmitVerificationRequest;
use App\Http\Requests\Worker\UpdateWorkerProfileRequest;
use App\Models\User;
use App\Models\WorkerProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class WorkerService
{
    /**
     * @return array{verification_status: string}
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function submitVerification(User $user, SubmitVerificationRequest $request): array
    {
        $workerProfile = $user->workerProfile;

        if ($workerProfile === null) {
            throw new BusinessRuleException('Worker profile not found.', 404);
        }

        if (in_array($workerProfile->verification_status, ['pending', 'approved'], true)) {
            throw new BusinessRuleException('You already have a pending or approved verification.');
        }

        $documentFields = ['government_id', 'barangay_certificate', 'selfie_with_id', 'skill_certificate'];
        $storedPaths    = [];

        foreach ($documentFields as $field) {
            if (! $request->hasFile($field)) {
                continue;
            }

            try {
                $ext  = $request->file($field)->getClientOriginalExtension();
                $path = "verifications/{$user->id}/{$field}.{$ext}";

                Storage::disk('public')->putFileAs(
                    "verifications/{$user->id}",
                    $request->file($field),
                    "{$field}.{$ext}"
                );

                $storedPaths[$field] = $path;
            } catch (Throwable $e) {
                Log::error('Verification file upload failed', [
                    'user_id'  => $user->id,
                    'field'    => $field,
                    'error'    => $e->getMessage(),
                ]);

                throw new BusinessRuleException('File upload failed. Please try again.', 500);
            }
        }

        DB::transaction(function () use ($workerProfile, $storedPaths): void {
            $workerProfile->verificationDocuments()->delete();

            foreach ($storedPaths as $documentType => $filePath) {
                $workerProfile->verificationDocuments()->create([
                    'document_type' => $documentType,
                    'file_path'     => $filePath,
                    'status'        => 'pending',
                ]);
            }

            $workerProfile->update([
                'verification_status'  => 'pending',
                'verification_remarks' => null,
            ]);
        });

        return ['verification_status' => 'pending'];
    }

    public function getVerificationStatus(User $user): array
    {
        $workerProfile = $user->workerProfile()->with('verificationDocuments')->first();

        if ($workerProfile === null) {
            throw new BusinessRuleException('Worker profile not found.', 404);
        }

        return [
            'verification_status' => $workerProfile->verification_status,
            'trust_tier'          => $workerProfile->trust_tier,
            'remarks'             => $workerProfile->verification_remarks,
            'documents'           => $workerProfile->verificationDocuments->map(fn ($doc) => [
                'type'   => $doc->document_type,
                'status' => $doc->status,
            ])->values()->all(),
        ];
    }

    public function updateProfile(User $user, UpdateWorkerProfileRequest $request): array
    {
        $workerProfile = $user->workerProfile;

        if ($workerProfile === null) {
            throw new BusinessRuleException('Worker profile not found.', 404);
        }

        DB::transaction(function () use ($workerProfile, $request): void {
            $workerProfile->update(array_filter([
                'bio'                 => $request->input('bio'),
                'availability_status' => $request->input('availability_status'),
            ], fn ($value) => $value !== null));

            if ($request->filled('category_ids')) {
                $workerProfile->serviceCategories()->sync($request->input('category_ids'));
            }
        });

        // NOTE: portfolio_photos storage is skipped — a worker_portfolio_photos
        // table is needed in a future migration to persist these properly.

        $workerProfile->load('serviceCategories');

        return [
            'bio'                 => $workerProfile->bio,
            'availability_status' => $workerProfile->availability_status,
            'verification_status' => $workerProfile->verification_status,
            'trust_tier'          => $workerProfile->trust_tier,
            'average_rating'      => $workerProfile->average_rating,
            'total_reviews'       => $workerProfile->total_reviews,
            'completed_jobs'      => $workerProfile->completed_jobs,
            'categories'          => $workerProfile->serviceCategories->map(fn ($cat) => [
                'id'   => $cat->id,
                'name' => $cat->name,
            ])->values()->all(),
        ];
    }
}
