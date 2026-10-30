<?php

declare(strict_types=1);

namespace App\Support;

final class TextNormalizer
{
    /** @var array<string, string> */
    private const LEET = [
        '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's',
        '7' => 't', '8' => 'b', '9' => 'g', '@' => 'a', '$' => 's',
        '!' => 'i', '|' => 'i', '+' => 't',
    ];

    /** @var array<string, string> */
    private const ACCENTS = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ñ' => 'n', 'ç' => 'c', 'ß' => 'ss',
    ];

    public function normalise(string $text): string
    {
        return $this->condense(mb_strtolower($text, 'UTF-8'), 3);
    }

    public function normaliseAggressive(string $text): string
    {
        return $this->condense(strtr(mb_strtolower($text, 'UTF-8'), self::LEET), 2);
    }

    public function contains(string $haystack, string $needle): bool
    {
        $needle = trim($needle);

        if ($needle === '') {
            return false;
        }

        $aggressiveHaystack = $this->normaliseAggressive($haystack);
        $aggressiveNeedle = $this->normaliseAggressive($needle);

        if (str_contains($aggressiveHaystack, $aggressiveNeedle)
            || str_contains($this->normalise($haystack), $this->normalise($needle))) {
            return true;
        }

        if (! str_contains($needle, ' ')) {
            return str_contains(
                str_replace(' ', '', $aggressiveHaystack),
                str_replace(' ', '', $aggressiveNeedle),
            );
        }

        return false;
    }

    public function countPhrase(string $haystack, string $phrase): int
    {
        $needle = $this->normaliseAggressive($phrase);

        if ($needle === '') {
            return 0;
        }

        $haystack = $this->normaliseAggressive($haystack);

        return preg_match_all('/' . preg_quote($needle, '/') . '/u', $haystack) ?: 0;
    }

    /** @return list<string> */
    public function tokens(string $text, bool $aggressive = false): array
    {
        $normalised = $aggressive ? $this->normaliseAggressive($text) : $this->normalise($text);

        if ($normalised === '') {
            return [];
        }

        return array_values(array_filter(
            explode(' ', $normalised),
            static fn (string $token): bool => $token !== '',
        ));
    }

    /** @param list<string> $patternWords */
    public function containsAllWords(string $haystack, array $patternWords): bool
    {
        if ($patternWords === []) {
            return false;
        }

        $tokens = $this->tokens($haystack, true);

        foreach ($patternWords as $word) {
            $needle = $this->normaliseAggressive($word);

            if ($needle !== '' && ! in_array($needle, $tokens, true)) {
                return false;
            }
        }

        return true;
    }

    private function condense(string $text, int $minRun): string
    {
        $text = strtr($text, self::ACCENTS);
        $repeats = $minRun >= 3 ? '2,' : '1,';
        $text = (string) preg_replace('/(.)\1{' . $repeats . '}/u', '$1', $text);
        $text = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
