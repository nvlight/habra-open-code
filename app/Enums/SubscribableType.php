<?php

namespace App\Enums;

enum SubscribableType: string
{
    case User = 'user';
    case Hub = 'hub';
    case Company = 'company';
}
