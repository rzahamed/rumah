<?php

namespace App\Policies;

class TeamMemberPolicy extends ContentPolicy
{
    protected string $permissionPrefix = 'team';
}
