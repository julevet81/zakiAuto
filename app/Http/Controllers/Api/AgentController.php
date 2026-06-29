<?php

namespace App\Policies;

use App\Models\Agent;
use App\Models\User;

class AgentPolicy
{
    /**
     * Agent management itself (creating/editing other agents, full list)
     * is an admin-only area — an agent never browses other agents. An
     * agent only ever needs to see/update their OWN record, handled in
     * view()/update() below.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('agents.view');
    }

    public function view(User $user, Agent $agent): bool
    {
        if ($user->can('agents.view')) {
            return true;
        }

        // An agent may view their own profile/statement.
        return $user->agent?->id === $agent->id;
    }

    public function create(User $user): bool
    {
        return $user->can('agents.create');
    }

    public function update(User $user, Agent $agent): bool
    {
        return $user->can('agents.update');
    }

    public function delete(User $user, Agent $agent): bool
    {
        return $user->can('agents.delete');
    }
}
