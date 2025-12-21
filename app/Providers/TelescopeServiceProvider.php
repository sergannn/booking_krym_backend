<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope::night();

        // Явно включаем Telescope (даже если TELESCOPE_ENABLED=false в окружении)
        // чтобы иметь доступ в проде под админом.
        config(['telescope.enabled' => true]);

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            return $isLocal ||
                   $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function ($user) {
            if (! $user) {
                return false;
            }

            // Разрешаем суперпользователям и админам (роль 1)
            if (method_exists($user, 'isSuperUser') && $user->isSuperUser()) {
                return true;
            }

            if (property_exists($user, 'moonshine_user_role_id') && (int) $user->moonshine_user_role_id === 1) {
                return true;
            }

            // Дополнительно — список email из конфигурации/окружения
            $allowedEmails = array_filter(explode(',', (string) config('telescope.allowed_emails', '')));

            return in_array($user->email, $allowedEmails, true);
        });
    }
}
