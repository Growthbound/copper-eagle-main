<?php

namespace App\Content\Enums;

enum RichNodeType: string
{
    case Heading = 'heading';
    case Paragraph = 'paragraph';
    case UnorderedList = 'unordered-list';
    case OrderedList = 'ordered-list';
    case Image = 'image';
    case Callout = 'callout';
    case Quote = 'quote';
    case Divider = 'divider';
}
