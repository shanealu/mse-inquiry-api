<?php

namespace App\Enums;

enum AuditAction: string
{
    case Created = 'created';
    case Viewed = 'viewed';
    case StatusChanged = 'status_changed';
    case Updated = 'updated';
}
