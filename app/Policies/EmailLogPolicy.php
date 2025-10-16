<?php

namespace App\Policies;

use App\Models\EmailLog;
use App\Models\User;

class EmailLogPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Apenas admins podem ver logs de emails
        return $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, EmailLog $emailLog): bool
    {
        // Apenas admins podem ver logs de emails
        return $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Logs de emails não podem ser criados manualmente
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, EmailLog $emailLog): bool
    {
        // Logs de emails não podem ser editados
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, EmailLog $emailLog): bool
    {
        // Logs de emails não podem ser excluídos
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, EmailLog $emailLog): bool
    {
        // Logs de emails não podem ser restaurados
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, EmailLog $emailLog): bool
    {
        // Logs de emails não podem ser excluídos permanentemente
        return false;
    }
}
