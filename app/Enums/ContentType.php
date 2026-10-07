<?php

namespace App\Enums;

enum ContentType: string
{
    case Quiz = 'quiz';
    case BlindTest = 'blind_test';
    case Drawing = 'drawing';
    case Phrase = 'phrase';
}
