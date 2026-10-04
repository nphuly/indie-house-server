<?php

namespace App\Enums;

enum Roles: string
{
    case ADMIN = 'admin';
    case STAFF = 'staff';
    case SUPER_ADMIN = 'super_admin';
    case USER = 'user';
}
