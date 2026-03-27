<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\FailedUpload;
use Illuminate\Auth\Access\HandlesAuthorization;

class FailedUploadPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:FailedUpload');
    }

    public function view(AuthUser $authUser, FailedUpload $failedUpload): bool
    {
        return $authUser->can('View:FailedUpload');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:FailedUpload');
    }

    public function update(AuthUser $authUser, FailedUpload $failedUpload): bool
    {
        return $authUser->can('Update:FailedUpload');
    }

    public function delete(AuthUser $authUser, FailedUpload $failedUpload): bool
    {
        return $authUser->can('Delete:FailedUpload');
    }

    public function restore(AuthUser $authUser, FailedUpload $failedUpload): bool
    {
        return $authUser->can('Restore:FailedUpload');
    }

    public function forceDelete(AuthUser $authUser, FailedUpload $failedUpload): bool
    {
        return $authUser->can('ForceDelete:FailedUpload');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:FailedUpload');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:FailedUpload');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:FailedUpload');
    }

    public function replicate(AuthUser $authUser, FailedUpload $failedUpload): bool
    {
        return $authUser->can('Replicate:FailedUpload');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:FailedUpload');
    }

}