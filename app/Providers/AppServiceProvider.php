<?php

namespace App\Providers;

use App\Models\Report;
use App\Policies\ReportPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Contract\Messaging;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Report::class, ReportPolicy::class);

        if ($this->app->environment('testing')) {
            $this->app->bind(Messaging::class, function () {
                return new class implements Messaging {
                    public function send(\Kreait\Firebase\Messaging\Message|array $message, bool $validateOnly = false): array { return []; }
                    public function sendMulticast(\Kreait\Firebase\Messaging\Message|array $message, mixed $tokens, bool $validateOnly = false): \Kreait\Firebase\Messaging\MulticastSendReport { return \Kreait\Firebase\Messaging\MulticastSendReport::withItems([]); }
                    public function sendAll(array|\Kreait\Firebase\Messaging\Messages $messages, bool $validateOnly = false): \Kreait\Firebase\Messaging\MulticastSendReport { return \Kreait\Firebase\Messaging\MulticastSendReport::withItems([]); }
                    public function validate(\Kreait\Firebase\Messaging\Message|array $message): array { return []; }
                    public function validateRegistrationTokens(mixed $tokens): array { return ['valid' => [], 'unknown' => [], 'invalid' => []]; }
                    public function subscribeToTopic(mixed $topic, mixed $tokens): array { return []; }
                    public function subscribeToTopics(iterable $topics, mixed $tokens): array { return []; }
                    public function unsubscribeFromTopic(mixed $topic, mixed $tokens): array { return []; }
                    public function unsubscribeFromTopics(array $topics, mixed $tokens): array { return []; }
                    public function unsubscribeFromAllTopics(mixed $tokens): array { return []; }
                    public function getAppInstance(mixed $token): \Kreait\Firebase\Messaging\AppInstance { throw new \RuntimeException('Not implemented'); }
                };
            });
        }
    }
}
