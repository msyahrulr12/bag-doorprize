<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DrawSession;
use Illuminate\Auth\Access\HandlesAuthorization;

class DrawSessionPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DrawSession');
    }

    public function view(AuthUser $authUser, DrawSession $drawSession): bool
    {
        return $authUser->can('View:DrawSession');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DrawSession');
    }

    public function update(AuthUser $authUser, DrawSession $drawSession): bool
    {
        return $authUser->can('Update:DrawSession');
    }

    public function delete(AuthUser $authUser, DrawSession $drawSession): bool
    {
        return $authUser->can('Delete:DrawSession');
    }

    public function restore(AuthUser $authUser, DrawSession $drawSession): bool
    {
        return $authUser->can('Restore:DrawSession');
    }

    public function forceDelete(AuthUser $authUser, DrawSession $drawSession): bool
    {
        return $authUser->can('ForceDelete:DrawSession');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DrawSession');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DrawSession');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DrawSession');
    }

    public function replicate(AuthUser $authUser, DrawSession $drawSession): bool
    {
        return $authUser->can('Replicate:DrawSession');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DrawSession');
    }

}