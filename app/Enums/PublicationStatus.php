<?php

namespace App\Enums;

enum PublicationStatus: string
{
    case Draft = 'draft';
    case Sandbox = 'sandbox';
    case Published = 'published';
}
