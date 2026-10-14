<?php

namespace App\Services\Worker;

use App\Exceptions\BusinessRuleException;
use App\Http\Requests\Worker\SubmitVerificationRequest;
use App\Http\Requests\Worker\UpdateWorkerProfileRequest;
use App\Models\User;
use App\Models\VerificationHistory;
use App\Models\WorkerPortfolioPhoto;
use App\Services\AdminNotificationService;
use App\Services\Media\MediaStorageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class WorkerService
{
    /**
     * @return array{verification_status: string}
     *
     * @throws HttpException
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

        $documentFields = [
            'government_id_front',
            'government_id_back',
            'barangay_certificate',
            'selfie_with_id',
            'skill_certificate',
        ];
        $storedPaths = [];

        foreach ($documentFields as $field) {
            if (! $request->hasFile($field)) {
                continue;
            }

            try {
                $ext = $request->file($field)->getClientOriginalExtension();
                $path = "verifications/{$user->id}/{$field}.{$ext}";

                $storedPaths[$field] = app(MediaStorageService::class)->storePrivateFile(
                    $request->file($field),
                    $path,
                );
            } catch (Throwable $e) {
                Log::error('Verification file upload failed', [
                    'user_id' => $user->id,
                    'field' => $field,
                    'error' => $e->getMessage(),
                ]);

                throw new BusinessRuleException('File upload failed. Please try again.', 500);
            }
        }

        $oldPaths = $workerProfile->verificationDocuments()->pluck('file_path')->all();
        foreach ($oldPaths as $oldPath) {
            app(MediaStorageService::class)->delete($oldPath);
        }

        DB::transaction(function () use ($workerProfile, $storedPaths): void {
            $workerProfile->verificationDocuments()->delete();

            foreach ($storedPaths as $documentType => $filePath) {
                $workerProfile->verificationDocuments()->create([
                    'document_type' => $documentType,
                    'file_path' => $filePath,
                    'status' => 'pending',
                ]);
            }

            $workerProfile->update([
                'verification_status' => 'pending',
                'verification_remarks' => null,
            ]);

            VerificationHistory::create([
                'worker_profile_id' => $workerProfile->id,
                'status' => 'pending',
                'remarks' => null,
            ]);
        });

        app(AdminNotificationService::class)->create(
            'verification_submitted',
            'Worker verification submitted',
            "{$user->name} submitted documents for verification.",
            ['url' => "/verifications/{$workerProfile->id}"],
        );

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
            'trust_tier' => $workerProfile->trust_tier,
            'availability_status' => $workerProfile->availability_status,
            'remarks' => $workerProfile->verification_remarks,
            'documents' => $workerProfile->verificationDocuments->map(fn ($doc) => [
                'type' => $doc->document_type,
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

        $keepPhotoIds = array_map(
            'intval',
            (array) $request->input('keep_portfolio_photo_ids', []),
        );
        $newPhotos = (array) $request->file('portfolio_photos', []);
        $syncPortfolioPhotos = $request->boolean('sync_portfolio_photos')
            || $request->has('keep_portfolio_photo_ids');
        $existingPhotoCount = $syncPortfolioPhotos
            ? count($keepPhotoIds)
            : $workerProfile->portfolioPhotos()->count();
        if ($existingPhotoCount + count($newPhotos) > 6) {
            throw ValidationException::withMessages([
                'portfolio_photos' => 'A maximum of six portfolio photos is allowed.',
            ]);
        }

        $storedPhotoPaths = [];
        try {
            foreach ($newPhotos as $photo) {
                $path = app(MediaStorageService::class)->storePublicFile(
                    $photo,
                    "worker-portfolios/{$user->id}/".\Illuminate\Support\Str::uuid().'.'.$photo->getClientOriginalExtension(),
                );
                $storedPhotoPaths[] = $path;
            }

            $removedPhotoPaths = DB::transaction(function () use (
                $workerProfile,
                $request,
                $keepPhotoIds,
                $storedPhotoPaths,
                $syncPortfolioPhotos,
            ): array {
                $workerProfile->update(array_filter([
                    'bio' => $request->input('bio'),
                    'availability_status' => $request->input('availability_status'),
                ], fn ($value) => $value !== null));

                if ($request->boolean('sync_categories') || $request->has('category_ids')) {
                    $workerProfile->serviceCategories()->sync($request->input('category_ids', []));
                }

                $removedPhotoPaths = [];
                if ($syncPortfolioPhotos || $storedPhotoPaths !== []) {
                    $photos = $workerProfile->portfolioPhotos()->get();
                    $keptPhotos = $syncPortfolioPhotos
                        ? $photos
                            ->filter(fn (WorkerPortfolioPhoto $photo) => in_array($photo->id, $keepPhotoIds, true))
                            ->values()
                        : $photos;

                    if ($syncPortfolioPhotos) {
                        $removedPhotoPaths = $photos
                            ->reject(fn (WorkerPortfolioPhoto $photo) => in_array($photo->id, $keepPhotoIds, true))
                            ->pluck('file_path')
                            ->all();

                        $workerProfile->portfolioPhotos()
                            ->whereNotIn('id', $keepPhotoIds ?: [0])
                            ->delete();
                    }

                    foreach ($keptPhotos as $order => $photo) {
                        $photo->update(['display_order' => $order]);
                    }

                    $nextOrder = $keptPhotos->count();
                    foreach ($storedPhotoPaths as $path) {
                        $workerProfile->portfolioPhotos()->create([
                            'file_path' => $path,
                            'display_order' => $nextOrder++,
                        ]);
                    }
                }

                return $removedPhotoPaths;
            });
        } catch (Throwable $exception) {
            app(MediaStorageService::class)->deleteMany($storedPhotoPaths);
            throw $exception;
        }

        if ($removedPhotoPaths !== []) {
            if (! app(MediaStorageService::class)->deleteMany($removedPhotoPaths)) {
                Log::warning('Unable to remove one or more deleted worker portfolio photos.', [
                    'worker_profile_id' => $workerProfile->id,
                    'paths' => $removedPhotoPaths,
                ]);
            }
        }

        $workerProfile->load(['serviceCategories', 'portfolioPhotos']);

        return [
            'bio' => $workerProfile->bio,
            'availability_status' => $workerProfile->availability_status,
            'verification_status' => $workerProfile->verification_status,
            'trust_tier' => $workerProfile->trust_tier,
            'average_rating' => $workerProfile->average_rating,
            'total_reviews' => $workerProfile->total_reviews,
            'completed_jobs' => $workerProfile->completed_jobs,
            'categories' => $workerProfile->serviceCategories->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
            ])->values()->all(),
            'portfolio_photos' => $workerProfile->portfolioPhotos->map(fn (WorkerPortfolioPhoto $photo) => [
                'id' => $photo->id,
                'url' => app(MediaStorageService::class)->publicUrl($photo->file_path),
                'display_order' => $photo->display_order,
            ])->values()->all(),
        ];
    }
}
