<?php

namespace App\Policies;

class PostPolicy extends ContentPolicy
{
    protected string $permissionPrefix = 'posts';
}
