<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ApprovalConfig;
use Illuminate\Auth\Access\HandlesAuthorization;

class ApprovalConfigPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ApprovalConfig');
    }

    public function view(AuthUser $authUser, ApprovalConfig $approvalConfig): bool
    {
        return $authUser->can('View:ApprovalConfig');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ApprovalConfig');
    }

    public function update(AuthUser $authUser, ApprovalConfig $approvalConfig): bool
    {
        return $authUser->can('Update:ApprovalConfig');
    }

    public function delete(AuthUser $authUser, ApprovalConfig $approvalConfig): bool
    {
        return $authUser->can('Delete:ApprovalConfig');
    }

    public function restore(AuthUser $authUser, ApprovalConfig $approvalConfig): bool
    {
        return $authUser->can('Restore:ApprovalConfig');
    }

    public function forceDelete(AuthUser $authUser, ApprovalConfig $approvalConfig): bool
    {
        return $authUser->can('ForceDelete:ApprovalConfig');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ApprovalConfig');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ApprovalConfig');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ApprovalConfig');
    }

    public function replicate(AuthUser $authUser, ApprovalConfig $approvalConfig): bool
    {
        return $authUser->can('Replicate:ApprovalConfig');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ApprovalConfig');
    }

}