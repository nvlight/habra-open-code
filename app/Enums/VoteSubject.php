<?php

namespace App\Enums;

enum VoteSubject: string
{
    case Publication = 'publications';
    case Comment = 'comments';
    case User = 'users';
}
