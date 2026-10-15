<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ReportAnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportAnalyticsController extends Controller
{
    public function __construct(
        private readonly ReportAnalyticsService $analytics,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $this->validatedFilters($request);

        return response()->json([
            'success' => true,
            'message' => 'Report analytics retrieved.',
            'data' => $this->analytics->overview(
                $filters['date_from'] ?? null,
                $filters['date_to'] ?? null,
            ),
        ]);
    }

    public function export(Request $request, string $type): StreamedResponse
    {
        abort_unless(in_array($type, ReportAnalyticsService::EXPORT_TYPES, true), 404);

        $filters = $this->validatedFilters($request);
        $from = $filters['date_from'] ?? null;
        $to = $filters['date_to'] ?? null;
        $suffix = $from || $to ? '-'.($from ?? 'all').'-'.($to ?? 'all') : '-all-time';
        $filename = 'hanapbuhay-'.$type.$suffix.'.csv';

        return response()->streamDownload(
            fn () => $this->analytics->writeCsv($type, $from, $to),
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    private function validatedFilters(Request $request): array
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date', 'before_or_equal:tomorrow'],
            'date_to' => ['nullable', 'date', 'before_or_equal:tomorrow'],
        ]);

        if (
            isset($filters['date_from'], $filters['date_to'])
            && CarbonImmutable::parse($filters['date_from'])->gt(CarbonImmutable::parse($filters['date_to']))
        ) {
            throw ValidationException::withMessages([
                'date_to' => 'The end date must be on or after the start date.',
            ]);
        }

        return $filters;
    }
}
