<?php

namespace App\Content\Enums;

enum ContentKind: string
{
    case Page = 'page';
    case Service = 'service';
    case Location = 'location';
    case Article = 'article';
    case Faq = 'faq';
    case Testimonial = 'testimonial';
    case Project = 'project';
    case TeamMember = 'team-member';
    case Navigation = 'navigation';
    case Footer = 'footer';

    /**
     * Kinds with a complete consumer: public routing or site-structure output.
     *
     * @return array<int, self>
     */
    public static function supportedCases(): array
    {
        return [
            self::Page,
            self::Service,
            self::Location,
            self::Article,
            self::Navigation,
            self::Footer,
        ];
    }

    public function isSupported(): bool
    {
        return in_array($this, self::supportedCases(), true);
    }
}
