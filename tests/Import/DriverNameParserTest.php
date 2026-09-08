<?php

declare(strict_types=1);

namespace App\Tests\Import;

use App\Import\DriverNameParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DriverNameParserTest extends TestCase
{
    private DriverNameParser $parser;

    protected function setUp(): void
    {
        $this->parser = new DriverNameParser();
    }

    /**
     * @param array{lastName: string, firstName: string, middleName?: ?string, birthDate?: ?string, birthYear?: ?int} $expected
     */
    #[DataProvider('nameCases')]
    public function testParse(string $raw, array $expected): void
    {
        $parsed = $this->parser->parse($raw);

        self::assertSame($expected['lastName'], $parsed->lastName);
        self::assertSame($expected['firstName'], $parsed->firstName);
        self::assertSame($expected['middleName'] ?? null, $parsed->middleName);
        self::assertSame($expected['birthDate'] ?? null, $parsed->birthDate?->format('Y-m-d'));
        self::assertSame($expected['birthYear'] ?? null, $parsed->birthYear);
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>}>
     */
    public static function nameCases(): iterable
    {
        yield 'plain three parts' => [
            'Абрамов Олег Юрьевич',
            ['lastName' => 'Абрамов', 'firstName' => 'Олег', 'middleName' => 'Юрьевич'],
        ];

        yield 'two parts, no patronymic' => [
            'АБЕРГАРД РОМАН',
            ['lastName' => 'Абергард', 'firstName' => 'Роман'],
        ];

        yield 'full birth date, dotted' => [
            'Абуладзе Арсен Андреевич 23.05.1985',
            ['lastName' => 'Абуладзе', 'firstName' => 'Арсен', 'middleName' => 'Андреевич', 'birthDate' => '1985-05-23'],
        ];

        yield 'ukrainian, apostrophe, "р.н," suffix' => [
            'Авер’янов Ігор Володимирович 06.09.1988 р.н,',
            ['lastName' => "Авер'янов", 'firstName' => 'Ігор', 'middleName' => 'Володимирович', 'birthDate' => '1988-09-06'],
        ];

        yield 'date glued to "р"' => [
            'Андріюк Сергій Леонідович 31.05.1966р',
            ['lastName' => 'Андріюк', 'firstName' => 'Сергій', 'middleName' => 'Леонідович', 'birthDate' => '1966-05-31'],
        ];

        yield 'comma before date' => [
            'Антипенко Олександр Олександрович,19.04.1992',
            ['lastName' => 'Антипенко', 'firstName' => 'Олександр', 'middleName' => 'Олександрович', 'birthDate' => '1992-04-19'],
        ];

        yield 'bare year with "г.р."' => [
            'Андрущенко Игорь Александрович 1970 г.р.',
            ['lastName' => 'Андрущенко', 'firstName' => 'Игорь', 'middleName' => 'Александрович', 'birthYear' => 1970],
        ];

        yield 'name doubled ua + ru, then date' => [
            'Ахтемов Валерій Аласович Ахтемов Валерий Аласович 04.02.1962',
            ['lastName' => 'Ахтемов', 'firstName' => 'Валерій', 'middleName' => 'Аласович', 'birthDate' => '1962-02-04'],
        ];

        yield 'two-digit year, no patronymic' => [
            'Зеленецкий Вадим 10.10.74',
            ['lastName' => 'Зеленецкий', 'firstName' => 'Вадим', 'birthDate' => '1974-10-10'],
        ];

        yield 'two-digit year glued to "г"' => [
            'Демьянчук Юрий 12.12.96г',
            ['lastName' => 'Демьянчук', 'firstName' => 'Юрий', 'birthDate' => '1996-12-12'],
        ];

        yield 'two-digit year with "г.р."' => [
            'Охинченко Сергей 10.01.72г.р.',
            ['lastName' => 'Охинченко', 'firstName' => 'Сергей', 'birthDate' => '1972-01-10'],
        ];

        yield 'slash separator, implausible year -> date dropped' => [
            'Васильев Михаил Михайлович / 01.04.22',
            ['lastName' => 'Васильев', 'firstName' => 'Михаил', 'middleName' => 'Михайлович', 'birthDate' => null],
        ];
    }
}
