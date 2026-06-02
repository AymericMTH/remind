<?php

namespace App\Actions\Lists;

enum DeleteStrategy: string
{
    case MoveToInbox = 'move_to_inbox';
    case Cascade = 'cascade';
}
