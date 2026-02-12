<?php

namespace App\Traits;

use App\Models\ApprovalConfig;
use App\Services\ApprovalService;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Modules\UserManagement\Models\User;

trait InteractsWithApprovals
{
    /**
     * Handle creation hook for CreateRecord page.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $resource = static::getResource();
        $resourceName = class_basename($resource);

        if (ApprovalConfig::isRequired($resourceName, 'create')) {
            $modelClass = $resource::getModel();
            ApprovalService::createRequest($resourceName, 'create', $modelClass, newData: $data);

            Notification::make()
                ->title('Creation request sent for approval.')
                ->success()
                ->send();

            $this->halt();
        }

        return parent::handleRecordCreation($data);
    }

    /**
     * Handle update hook for EditRecord page.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $resource = static::getResource();
        $resourceName = class_basename($resource);

        if (ApprovalConfig::isRequired($resourceName, 'update')) {
            // Compare data to see if changed (optional optimization)
            ApprovalService::createRequest($resourceName, 'update', $record, newData: $data, originalData: $record->toArray());

            Notification::make()
                ->title("Update request sent for approval.")
                ->success()
                ->send();

            $this->halt();
        }

        return parent::handleRecordUpdate($record, $data);
    }

    /**
     * Helper to wrap Table Actions that require approval.
     */
    public static function wrapTableAction(\Filament\Actions\Action $action, string $resourceName)
    {
        $actionName = $action->getName();

        return $action
            ->action(function (Model $record, array $data) use ($action, $resourceName, $actionName) {
                if (ApprovalConfig::isRequired($resourceName, $actionName)) {
                    ApprovalService::createRequest($resourceName, $actionName, $record, newData: $data ?: $record->toArray());

                    Notification::make()
                        ->title(ucfirst($actionName) . ' request sent for approval.')
                        ->success()
                        ->send();

                    return;
                }

                // If no approval required, execute original action logic
                // This is tricky because we can't easily call the protected 'action' from outside
                // Best is to use this helper when DEFINING the action if we know it needs approval
            });
    }
}
