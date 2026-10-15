<?php

namespace App\Services\Admin;

use App\Models\Booking;
use App\Models\JobPost;
use App\Models\RatingReview;
use App\Models\Report;
use App\Models\User;
use App\Models\WorkerProfile;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class ReportAnalyticsService
{
    public const EXPORT_TYPES = [
        'users',
        'bookings',
        'job-posts',
        'reports',
        'reviews',
        'verifications',
    ];

    public function overview(?string $dateFrom, ?string $dateTo): array
    {
        $end = $dateTo
            ? CarbonImmutable::parse($dateTo)->endOfDay()
            : CarbonImmutable::today()->endOfDay();
        $start = $dateFrom
            ? CarbonImmutable::parse($dateFrom)->startOfDay()
            : $end->startOfDay()->subDays(29);
        $monthly = $start->diffInDays($end) > 61;

        $users = $this->periodQuery(User::query(), 'created_at', $start, $end);
        $bookings = $this->periodQuery(Booking::query(), 'created_at', $start, $end);
        $jobPosts = $this->periodQuery(JobPost::query(), 'created_at', $start, $end);
        $reports = $this->periodQuery(Report::query(), 'created_at', $start, $end);
        $reviews = $this->periodQuery(RatingReview::query(), 'created_at', $start, $end);
        $verifications = $this->periodQuery(WorkerProfile::query(), 'created_at', $start, $end);

        return [
            'period' => [
                'date_from' => $start->toDateString(),
                'date_to' => $end->toDateString(),
                'interval' => $monthly ? 'month' : 'day',
            ],
            'summary' => [
                'users' => (clone $users)->count(),
                'bookings' => (clone $bookings)->count(),
                'job_posts' => (clone $jobPosts)->count(),
                'reports' => (clone $reports)->count(),
                'reviews' => (clone $reviews)->count(),
                'average_review_score' => round((float) (clone $reviews)->avg('score'), 2),
            ],
            'users_by_role' => $this->groupCounts(clone $users, 'role'),
            'bookings_by_status' => $this->groupCounts(clone $bookings, 'status'),
            'job_posts_by_category' => $this->jobPostsByCategory($start, $end),
            'reports_by_status' => $this->groupCounts(clone $reports, 'status'),
            'reports_by_reason' => $this->groupCounts(clone $reports, 'reason'),
            'verifications_by_status' => $this->groupCounts(clone $verifications, 'verification_status'),
            'trend' => $this->trend($start, $end, $monthly),
        ];
    }

    public function writeCsv(string $type, ?string $dateFrom, ?string $dateTo): void
    {
        $stream = fopen('php://output', 'wb');
        if ($stream === false) {
            throw new \RuntimeException('Unable to open the report export stream.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        $query = $this->exportQuery($type);
        $this->applyExportPeriod($query, $type, $dateFrom, $dateTo);
        fputcsv($stream, $this->exportHeaders($type));

        foreach ($query->orderBy($this->exportTable($type).'.id')->cursor() as $row) {
            fputcsv($stream, array_map(
                fn (mixed $value): string => $this->safeCsvValue($value),
                (array) $row,
            ));
        }

        fclose($stream);
    }

    private function periodQuery(Builder $query, string $column, CarbonImmutable $start, CarbonImmutable $end): Builder
    {
        return $query->whereBetween($column, [$start, $end]);
    }

    private function groupCounts(Builder $query, string $column): array
    {
        return $query
            ->select($column)
            ->selectRaw('COUNT(*) as total')
            ->groupBy($column)
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'key' => $row->{$column} ?? 'unknown',
                'count' => (int) $row->total,
            ])
            ->all();
    }

    private function jobPostsByCategory(CarbonImmutable $start, CarbonImmutable $end): array
    {
        return DB::table('job_posts')
            ->leftJoin('service_categories', 'service_categories.id', '=', 'job_posts.service_category_id')
            ->whereNull('job_posts.deleted_at')
            ->whereBetween('job_posts.created_at', [$start, $end])
            ->selectRaw("COALESCE(service_categories.name, 'Uncategorized') as category")
            ->selectRaw('COUNT(*) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'key' => $row->category,
                'count' => (int) $row->total,
            ])
            ->all();
    }

    private function trend(CarbonImmutable $start, CarbonImmutable $end, bool $monthly): array
    {
        $models = [
            'users' => User::query(),
            'bookings' => Booking::query(),
            'job_posts' => JobPost::query(),
            'reports' => Report::query(),
            'reviews' => RatingReview::query(),
        ];
        $counts = [];

        foreach ($models as $key => $query) {
            $dailyCounts = $this->periodQuery($query, 'created_at', $start, $end)
                ->selectRaw('DATE(created_at) as activity_date')
                ->selectRaw('COUNT(*) as total')
                ->groupBy('activity_date')
                ->pluck('total', 'activity_date');

            foreach ($dailyCounts as $date => $total) {
                $bucket = $monthly
                    ? CarbonImmutable::parse($date)->format('Y-m')
                    : CarbonImmutable::parse($date)->format('Y-m-d');
                $counts[$key][$bucket] = ($counts[$key][$bucket] ?? 0) + (int) $total;
            }
        }

        $buckets = [];
        $cursor = $monthly ? $start->startOfMonth() : $start->startOfDay();
        $last = $monthly ? $end->startOfMonth() : $end->startOfDay();
        while ($cursor <= $last) {
            $key = $monthly ? $cursor->format('Y-m') : $cursor->format('Y-m-d');
            $buckets[] = [
                'key' => $key,
                'label' => $monthly ? $cursor->format('M Y') : $cursor->format('M j'),
                'users' => (int) ($counts['users'][$key] ?? 0),
                'bookings' => (int) ($counts['bookings'][$key] ?? 0),
                'job_posts' => (int) ($counts['job_posts'][$key] ?? 0),
                'reports' => (int) ($counts['reports'][$key] ?? 0),
                'reviews' => (int) ($counts['reviews'][$key] ?? 0),
            ];
            $cursor = $monthly ? $cursor->addMonth() : $cursor->addDay();
        }

        return $buckets;
    }

    private function exportQuery(string $type): QueryBuilder
    {
        return match ($type) {
            'users' => DB::table('users')
                ->leftJoin('barangays', 'barangays.id', '=', 'users.barangay_id')
                ->whereNull('users.deleted_at')
                ->select([
                    'users.id',
                    'users.name',
                    'users.role',
                    'users.is_active',
                    'barangays.name as barangay',
                    'users.email_verified_at',
                    'users.created_at',
                ]),
            'bookings' => DB::table('bookings')
                ->leftJoin('users as clients', 'clients.id', '=', 'bookings.client_id')
                ->leftJoin('users as workers', 'workers.id', '=', 'bookings.worker_id')
                ->leftJoin('service_categories', 'service_categories.id', '=', 'bookings.service_category_id')
                ->whereNull('bookings.deleted_at')
                ->select([
                    'bookings.id',
                    'bookings.booking_code',
                    'bookings.status',
                    'clients.name as client_name',
                    'workers.name as worker_name',
                    'service_categories.name as service_category',
                    'bookings.scheduled_at',
                    'bookings.started_at',
                    'bookings.completed_at',
                    'bookings.cancelled_by',
                    'bookings.created_at',
                ]),
            'job-posts' => DB::table('job_posts')
                ->leftJoin('worker_profiles', 'worker_profiles.id', '=', 'job_posts.worker_profile_id')
                ->leftJoin('users as workers', 'workers.id', '=', 'worker_profiles.user_id')
                ->leftJoin('service_categories', 'service_categories.id', '=', 'job_posts.service_category_id')
                ->whereNull('job_posts.deleted_at')
                ->select([
                    'job_posts.id',
                    'job_posts.title',
                    'workers.name as worker_name',
                    'service_categories.name as service_category',
                    'job_posts.rate_amount',
                    'job_posts.rate_type',
                    'job_posts.is_available',
                    'job_posts.is_active',
                    'job_posts.created_at',
                ]),
            'reports' => DB::table('reports')
                ->leftJoin('users as reporters', 'reporters.id', '=', 'reports.reported_by')
                ->leftJoin('users as reported_users', 'reported_users.id', '=', 'reports.reported_user')
                ->leftJoin('bookings', 'bookings.id', '=', 'reports.booking_id')
                ->select([
                    'reports.id',
                    'reports.reason',
                    'reports.status',
                    'reporters.name as reporter_name',
                    'reported_users.name as reported_user_name',
                    'bookings.booking_code',
                    'reports.resolution_action',
                    'reports.resolved_at',
                    'reports.created_at',
                ]),
            'reviews' => DB::table('ratings_reviews')
                ->leftJoin('bookings', 'bookings.id', '=', 'ratings_reviews.booking_id')
                ->leftJoin('users as reviewers', 'reviewers.id', '=', 'ratings_reviews.rated_by')
                ->leftJoin('users as reviewed_users', 'reviewed_users.id', '=', 'ratings_reviews.rated_user')
                ->select([
                    'ratings_reviews.id',
                    'bookings.booking_code',
                    'reviewers.name as reviewer_name',
                    'reviewed_users.name as reviewed_user_name',
                    'ratings_reviews.score',
                    'ratings_reviews.created_at',
                ]),
            'verifications' => DB::table('worker_profiles')
                ->leftJoin('users', 'users.id', '=', 'worker_profiles.user_id')
                ->select([
                    'worker_profiles.id',
                    'users.name as worker_name',
                    'worker_profiles.verification_status',
                    'worker_profiles.trust_tier',
                    'worker_profiles.average_rating',
                    'worker_profiles.total_reviews',
                    'worker_profiles.completed_jobs',
                    'worker_profiles.verified_at',
                    'worker_profiles.created_at',
                ]),
            default => throw new \InvalidArgumentException('Unsupported report export type.'),
        };
    }

    private function exportHeaders(string $type): array
    {
        return match ($type) {
            'users' => ['User ID', 'Name', 'Role', 'Active', 'Barangay', 'Email Verified At', 'Created At'],
            'bookings' => ['Booking ID', 'Booking Code', 'Status', 'Client', 'Worker', 'Service Category', 'Scheduled At', 'Started At', 'Completed At', 'Cancelled By', 'Created At'],
            'job-posts' => ['Job Post ID', 'Title', 'Worker', 'Service Category', 'Rate Amount', 'Rate Type', 'Available', 'Active', 'Created At'],
            'reports' => ['Report ID', 'Reason', 'Status', 'Reporter', 'Reported User', 'Booking Code', 'Resolution Action', 'Resolved At', 'Created At'],
            'reviews' => ['Review ID', 'Booking Code', 'Reviewer', 'Reviewed User', 'Score', 'Created At'],
            'verifications' => ['Worker Profile ID', 'Worker', 'Verification Status', 'Trust Tier', 'Average Rating', 'Total Reviews', 'Completed Jobs', 'Verified At', 'Profile Created At'],
            default => throw new \InvalidArgumentException('Unsupported report export type.'),
        };
    }

    private function exportTable(string $type): string
    {
        return match ($type) {
            'users' => 'users',
            'bookings' => 'bookings',
            'job-posts' => 'job_posts',
            'reports' => 'reports',
            'reviews' => 'ratings_reviews',
            'verifications' => 'worker_profiles',
            default => throw new \InvalidArgumentException('Unsupported report export type.'),
        };
    }

    private function applyExportPeriod(QueryBuilder $query, string $type, ?string $dateFrom, ?string $dateTo): void
    {
        $createdAt = $this->exportTable($type).'.created_at';
        if ($dateFrom !== null) {
            $query->whereDate($createdAt, '>=', $dateFrom);
        }
        if ($dateTo !== null) {
            $query->whereDate($createdAt, '<=', $dateTo);
        }
    }

    private function safeCsvValue(mixed $value): string
    {
        $value = $value === null ? '' : (string) $value;
        if (preg_match('/^[\s]*[=+\-@]/u', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }
}
