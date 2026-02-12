<?php

namespace App\Services;

use App\Models\Approval;
use App\Models\ApprovalConfig;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ApprovalService
{
    /**
     * Create a pending approval request.
     */
    public static function createRequest(string $resource, string $action, $model, array $newData = [], ?array $originalData = []): Approval
    {
        $modelType = null;
        $modelId = null;

        if ($model instanceof Model) {
            $modelType = get_class($model);
            $modelId = $model->getKey();
        } elseif (is_string($model)) {
            $modelType = $model;
        }

        $approval = Approval::create([
            'model_type' => $modelType,
            'model_id' => $modelId,
            'resource' => $resource,
            'action' => $action,
            'original_data' => $originalData ?: ($model instanceof Model ? $model->toArray() : null),
            'new_data' => $newData,
            'user_id' => Auth::id(),
            'status' => Approval::STATUS_PENDING,
        ]);

        // Automatically notify approvers
        $approverRole = ApprovalConfig::getApproverRole($resource, $action);
        $users = \Modules\UserManagement\Models\User::role($approverRole)->get();

        Notification::make()
            ->title("New {$action} request for {$resource}")
            ->body("A new approval request has been submitted by " . Auth::user()->name)
            ->info()
            ->sendToDatabase($users);

        return $approval;
    }

    /**
     * Check if a user can approve a specific request.
     */
    public static function canApprove(Approval $approval): bool
    {
        if ($approval->user_id === Auth::id()) {
            return false; // Maker cannot be Checker
        }

        $approverRole = ApprovalConfig::getApproverRole($approval->resource, $approval->action);
        return Auth::user()->hasRole($approverRole);
    }

    /**
     * Map of resource names to their respective handler service/class.
     */
    protected static array $customHandlers = [
        'PointCorrection' => \App\Services\PointService::class,
    ];

    /**
     * Approve a request and execute the action.
     */
    public static function approve(Approval $approval): bool
    {
        try {
            \DB::beginTransaction();

            // Handle Custom Resource Actions
            if (isset(self::$customHandlers[$approval->resource])) {
                $handlerClass = self::$customHandlers[$approval->resource];
                $method = $approval->action;
                $handler = app($handlerClass);

                if (method_exists($handler, $method)) {
                    $handler->$method($approval->new_data);
                } else {
                    // Fallback to executeCorrection if method submit is called for PointCorrection
                    if ($approval->resource === 'PointCorrection' && $method === 'submit') {
                        $handler->executeCorrection($approval->new_data);
                    }
                }
            } else {
                // Handle Standard Model CRUD Actions
                switch ($approval->action) {
                    case 'create':
                        $modelClass = $approval->model_type;
                        if (!$modelClass) {
                            throw new \Exception("Model type is missing for creation approval.");
                        }
                        $modelClass::create($approval->new_data);
                        break;

                    case 'update':
                        $model = $approval->model_type::find($approval->model_id);
                        if ($model) {
                            $model->update($approval->new_data);
                        }
                        break;

                    case 'delete':
                        $model = $approval->model_type::find($approval->model_id);
                        if ($model) {
                            $model->delete();
                        }
                        break;

                    default:
                        // Handle other custom cases
                        break;
                }
            }

            $approval->update([
                'status' => Approval::STATUS_APPROVED,
                'approver_id' => Auth::id(),
                'action_at' => now(),
            ]);

            \DB::commit();
            return true;
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Approval execution failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Reject a request.
     */
    public static function reject(Approval $approval, string $reason): void
    {
        $approval->update([
            'status' => Approval::STATUS_REJECTED,
            'approver_id' => Auth::id(),
            'action_at' => now(),
            'reason' => $reason,
        ]);
    }
}
