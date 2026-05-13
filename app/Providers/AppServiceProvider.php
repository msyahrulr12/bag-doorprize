<?php

namespace App\Providers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Modules\UserManagement\Models\User;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Login;
use OwenIt\Auditing\Models\Audit;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->register(\Modules\LogManagement\Providers\LogManagementServiceProvider::class);
        $this->app->register(\Modules\UserManagement\Providers\UserManagementServiceProvider::class);
        $this->app->register(\Modules\ProcessManagement\Providers\ProcessManagementServiceProvider::class);
        $this->app->bind(Authenticatable::class, User::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Filament\Actions\Action::configureUsing(function (\Filament\Actions\Action $action) {
            $this->applyApprovalLogic($action);
        });

        \Filament\Actions\BulkAction::configureUsing(function (\Filament\Actions\BulkAction $action) {
            // Optional: Handle bulk actions if needed
        });

        Event::listen(Login::class, function (Login $event) {
            Audit::create([
                'user_type'      => get_class($event->user),
                'user_id'        => $event->user->getAuthIdentifier(),
                'event'          => 'login',
                'auditable_type' => get_class($event->user),
                'auditable_id'   => $event->user->getAuthIdentifier(),
                'old_values'     => [],
                'new_values'     => [],
                'url'            => request()->fullUrl(),
                'ip_address'     => request()->ip(),
                'user_agent'     => request()->userAgent(),
                'tags'           => 'auth,login',
            ]);
        });
    }

    private function applyApprovalLogic($action): void
    {
        if ($action instanceof \Filament\Actions\DeleteAction) {
            $action->before(function (\Illuminate\Database\Eloquent\Model $record, \Filament\Actions\DeleteAction $action) {
                // For table actions, we can get the livewire component
                $livewire = method_exists($action, 'getLivewire') ? $action->getLivewire() : null;

                // If it's a table action but getLivewire returns table/container
                if (method_exists($action, 'getTable') && $action->getTable()) {
                    $livewire = $action->getTable()->getLivewire();
                }

                $resource = ($livewire && method_exists($livewire, 'getResource')) ? $livewire->getResource() : null;

                if (!$resource) {
                    return;
                }

                $resourceName = class_basename($resource);

                if (\App\Models\ApprovalConfig::isRequired($resourceName, 'delete')) {
                    \App\Services\ApprovalService::createRequest($resourceName, 'delete', $record);

                    \Filament\Notifications\Notification::make()
                        ->title('Deletion request sent for approval.')
                        ->success()
                        ->send();

                    $action->halt();
                }
            });
        }
    }
}
