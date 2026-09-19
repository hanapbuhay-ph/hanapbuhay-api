<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $defaultReasons = [
            [
                'code' => 'no_show',
                'label' => 'No Show',
                'description' => 'The other party did not show up or failed to attend the agreed schedule.',
                'is_active' => true,
            ],
            [
                'code' => 'unsatisfactory_work',
                'label' => 'Unsatisfactory Work',
                'description' => 'The service delivered did not meet the agreed quality or expected standards.',
                'is_active' => true,
            ],
            [
                'code' => 'misconduct',
                'label' => 'Misconduct',
                'description' => 'The other party acted unprofessionally or inappropriately.',
                'is_active' => true,
            ],
            [
                'code' => 'non_payment',
                'label' => 'Non-Payment',
                'description' => 'The other party failed to pay for services rendered or agreed payments.',
                'is_active' => true,
            ],
            [
                'code' => 'unsafe_environment',
                'label' => 'Unsafe Environment',
                'description' => 'The surrounding conditions or conduct created an unsafe or unsuitable environment.',
                'is_active' => true,
            ],
            [
                'code' => 'abusive_behavior',
                'label' => 'Abusive Behavior',
                'description' => 'The other party engaged in abusive, threatening, or harmful behavior.',
                'is_active' => true,
            ],
            [
                'code' => 'false_information',
                'label' => 'False Information',
                'description' => 'The other party provided false or misleading information.',
                'is_active' => true,
            ],
            [
                'code' => 'other',
                'label' => 'Other',
                'description' => 'Other issue not covered by the standard reasons listed here.',
                'is_active' => true,
            ],
        ];

        foreach ($defaultReasons as $reason) {
            \App\Models\ReportReason::query()->firstOrCreate(
                ['code' => $reason['code']],
                $reason,
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('report_reasons');
    }
};
