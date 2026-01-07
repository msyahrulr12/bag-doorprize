<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Prize;
use Illuminate\Auth\Access\HandlesAuthorization;

class PrizePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Prize');
    }

    public function view(AuthUser $authUser, Prize $prize): bool
    {
        return $authUser->can('View:Prize');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Prize');
    }

    public function update(AuthUser $authUser, Prize $prize): bool
    {
        return $authUser->can('Update:Prize');
    }

    public function delete(AuthUser $authUser, Prize $prize): bool
    {
        return $authUser->can('Delete:Prize');
    }

    public function restore(AuthUser $authUser, Prize $prize): bool
    {
        return $authUser->can('Restore:Prize');
    }

    public function forceDelete(AuthUser $authUser, Prize $prize): bool
    {
        return $authUser->can('ForceDelete:Prize');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Prize');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Prize');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Prize');
    }

    public function replicate(AuthUser $authUser, Prize $prize): bool
    {
        return $authUser->can('Replicate:Prize');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Prize');
    }

}