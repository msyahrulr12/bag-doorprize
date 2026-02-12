<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Approval;
use Illuminate\Auth\Access\HandlesAuthorization;

class ApprovalPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Approval');
    }

    public function view(AuthUser $authUser, Approval $approval): bool
    {
        return $authUser->can('View:Approval');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Approval');
    }

    public function update(AuthUser $authUser, Approval $approval): bool
    {
        return $authUser->can('Update:Approval');
    }

    public function delete(AuthUser $authUser, Approval $approval): bool
    {
        return $authUser->can('Delete:Approval');
    }

    public function restore(AuthUser $authUser, Approval $approval): bool
    {
        return $authUser->can('Restore:Approval');
    }

    public function forceDelete(AuthUser $authUser, Approval $approval): bool
    {
        return $authUser->can('ForceDelete:Approval');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Approval');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Approval');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Approval');
    }

    public function replicate(AuthUser $authUser, Approval $approval): bool
    {
        return $authUser->can('Replicate:Approval');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Approval');
    }

}