<?php

declare(strict_types=1);

namespace App\Import;

/**
 * Turns a raw "last name / first name / patronymic (+ optional birth date)" string
 * from column C of the source workbook into a structured {@see ParsedName}.
 *
 * Pure, stateless, no dependencies — unit-tested in isolation.
 *
 * The source data is hand-typed and inconsistent, so instead of one big regex we
 * peel the string apart in small, individually testable steps:
 *   normalize → cut the birth date/year off the end → split what's left into F/I/O.
 */
final class DriverNameParser
{
    /**
     * A full date glued to the end of the string, optionally followed by a
     * "year of birth" marker. The year may be 2 or 4 digits. Matches the trailing part of:
     *   "... 23.05.1985", "... 06.09.1988 р.н,", "... 31.05.1966р",
     *   "... 05.05.2000 г.р.", "...,19.04.1992", "... 10.10.74", "... / 01.04.22"
     */
    private const string BIRTH_DATE_RE =
        '~[\s,/]*(\d{1,2})[.\-/](\d{1,2})[.\-/](\d{2,4})\s*(?:р\.?\s?н\.?|г\.?\s?р\.?|р\.?|г\.?)?[.,]?\s*$~u';

    /** Youngest / oldest birth year we accept — a rental-car driver is an adult, not a centenarian. */
    private const int MIN_BIRTH_YEAR = 1930;
    private const int MAX_BIRTH_YEAR = 2008;

    /** A bare 4-digit year at the end: "... 1994", "... 1970 г.р.". */
    private const string BIRTH_YEAR_RE =
        '~[\s,]*((?:19|20)\d\d)\s*(?:р\.?\s?н\.?|г\.?\s?р\.?)?[.,]?\s*$~u';

    public function parse(string $raw): ParsedName
    {
        $s = $this->normalize($raw);

        $birthDate = $this->extractBirthDate($s);                       // trims the match off $s
        $birthYear = null === $birthDate ? $this->extractBirthYear($s) : null;

        [$lastName, $firstName, $middleName] = $this->splitFio($s);

        return new ParsedName(
            lastName: $this->titleCase($lastName),
            firstName: $this->titleCase($firstName),
            middleName: null === $middleName ? null : $this->titleCase($middleName),
            birthDate: $birthDate,
            birthYear: $birthYear,
        );
    }

    private function normalize(string $raw): string
    {
        $s = str_replace(['’', 'ʼ', '‘', '`', '´'], "'", trim($raw));   // unify apostrophes
        $s = preg_replace('~,(?=\S)~u', ', ', $s);                      // "Ivanov,19.04" -> "Ivanov, 19.04"
        $s = preg_replace('~\s+~u', ' ', $s);                           // collapse whitespace / newlines / tabs

        return trim($s);
    }

    private function extractBirthDate(string &$s): ?\DateTimeImmutable
    {
        if (!preg_match(self::BIRTH_DATE_RE, $s, $m)) {
            return null;
        }

        // Whatever matched is a date-like token — strip it from the name regardless
        // of whether it turns out to be a usable birth date.
        $s = trim((string) preg_replace(self::BIRTH_DATE_RE, '', $s));

        $day = (int) $m[1];
        $month = (int) $m[2];
        $year = $this->expandYear((int) $m[3]);

        $date = \DateTimeImmutable::createFromFormat('!d.m.Y', sprintf('%02d.%02d.%04d', $day, $month, $year));

        // createFromFormat() silently rolls invalid dates over (30.02 -> 02.03),
        // so verify it round-trips, and reject implausible birth years.
        if (!$date instanceof \DateTimeImmutable
            || $date->format('j.n.Y') !== sprintf('%d.%d.%d', $day, $month, $year)
            || $year < self::MIN_BIRTH_YEAR || $year > self::MAX_BIRTH_YEAR
        ) {
            return null;
        }

        return $date;
    }

    /** "74" -> 1974, "05" -> 2005, "1988" -> 1988. Pivot at 30. */
    private function expandYear(int $year): int
    {
        if ($year >= 100) {
            return $year;
        }

        return $year >= 30 ? 1900 + $year : 2000 + $year;
    }

    private function extractBirthYear(string &$s): ?int
    {
        if (!preg_match(self::BIRTH_YEAR_RE, $s, $m)) {
            return null;
        }

        $s = trim((string) preg_replace(self::BIRTH_YEAR_RE, '', $s));

        return (int) $m[1];
    }

    /**
     * @return array{0: string, 1: string, 2: ?string}
     */
    private function splitFio(string $s): array
    {
        $words = preg_split('~\s+~u', trim($s), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        // Some rows repeat the whole name in both languages
        // ("Ахтемов Валерій Аласович Ахтемов Валерий Аласович") — keep the first half.
        if (6 === count($words) && $this->looksDoubled($words)) {
            $words = \array_slice($words, 0, 3);
        }

        return match (true) {
            count($words) >= 3 => [$words[0], $words[1], $words[2]],
            2 === count($words) => [$words[0], $words[1], null],
            1 === count($words) => [$words[0], '', null],
            default => ['', '', null],
        };
    }

    /**
     * @param list<string> $words
     */
    private function looksDoubled(array $words): bool
    {
        return isset($words[3]) && mb_strtolower($words[0], 'UTF-8') === mb_strtolower($words[3], 'UTF-8');
    }

    /**
     * Uppercase the first letter of each hyphen-separated part, lowercase the rest.
     * Done manually (not MB_CASE_TITLE) so an apostrophe does not start a new "word":
     * "авер'янов" must become "Авер'янов", not "Авер'Янов".
     */
    private function titleCase(string $word): string
    {
        if ('' === $word) {
            return '';
        }

        return implode('-', array_map(
            static function (string $part): string {
                $part = mb_strtolower($part, 'UTF-8');

                return mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8')
                    . mb_substr($part, 1, null, 'UTF-8');
            },
            explode('-', $word),
        ));
    }
}
