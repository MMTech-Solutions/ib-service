<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Support;

use App\Features\Rules\Catalog\Exceptions\InvalidRuleSlugException;

final class RuleSlug
{
    public static function fromName(string $name): string
    {
        $ascii = self::ascii($name);
        $slug = strtolower(trim($ascii));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        if ($slug === '' || strlen($slug) > 64) {
            throw InvalidRuleSlugException::forName($name);
        }

        return $slug;
    }

    private static function ascii(string $value): string
    {
        if (function_exists('transliterator_transliterate')) {
            $transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII', $value);
            if (is_string($transliterated) && $transliterated !== '') {
                return $transliterated;
            }
        }

        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return is_string($converted) ? $converted : $value;
    }
}
