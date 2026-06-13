<?php

declare(strict_types=1);

namespace Doctrine\Tests\RST;

use Doctrine\RST\Configuration;
use Doctrine\RST\Kernel;
use Doctrine\RST\Parser;
use PHPUnit\Framework\TestCase;

use function preg_match;
use function setlocale;
use function trim;

use const LC_CTYPE;

/**
 * When PHP runs under a UTF-8 locale, it builds locale-specific PCRE character
 * tables in which the byte 0xA0 (the second byte of Cyrillic letters such as
 * "Р" = D0 A0) is classified as whitespace. Span regexes that lack the "u"
 * modifier then match that byte and split the multibyte character, producing
 * invalid UTF-8 or losing content. These tests guard against that regression.
 */
class MultibyteSpanTest extends TestCase
{
    /** @var string */
    private $previousLocale;

    protected function setUp(): void
    {
        $previousLocale       = setlocale(LC_CTYPE, '0');
        $this->previousLocale = $previousLocale === false ? 'C' : $previousLocale;

        // Force PHP to build locale-specific PCRE character tables for a UTF-8
        // locale; going through "C" first guarantees the locale actually
        // changes so the tables are rebuilt.
        setlocale(LC_CTYPE, 'C');

        $utf8Activated = false;
        foreach (['C.UTF-8', 'en_US.UTF-8', 'en_US.utf8', 'C.utf8'] as $locale) {
            if (setlocale(LC_CTYPE, $locale) !== false) {
                $utf8Activated = true;

                break;
            }
        }

        if ($utf8Activated && preg_match('/\s/', "\xa0") === 1) {
            return;
        }

        setlocale(LC_CTYPE, $this->previousLocale);

        self::markTestSkipped('No UTF-8 locale that treats 0xA0 as whitespace is available.');
    }

    protected function tearDown(): void
    {
        setlocale(LC_CTYPE, $this->previousLocale);
    }

    public function testNamedReferenceWithMultibyteLabelSpanningLines(): void
    {
        $rst = <<<'EOF'
see `Рабочий процесс
запроса-ответа`_

.. _`Рабочий процесс запроса-ответа`: https://example.com/workflow
EOF;

        $result = trim((new Parser(new Kernel(new Configuration())))->parse($rst)->render());

        self::assertSame('<p>see <a href="https://example.com/workflow">Рабочий процесс запроса-ответа</a></p>', $result);
    }

    public function testStandaloneHyperlinkWithMultibyteInPath(): void
    {
        $result = trim((new Parser(new Kernel(new Configuration())))->parse('visit https://example.com/Раздел now')->render());

        self::assertSame('<p>visit <a href="https://example.com/Раздел">https://example.com/Раздел</a> now</p>', $result);
    }
}
