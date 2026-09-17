<?php

declare(strict_types=1);

namespace FreshetFeeds\Tests\Unit;

use Brain\Monkey\Functions;
use FreshetFeeds\Admin\LicenseSection;
use FreshetFeeds\License\LicenseClient;
use FreshetFeeds\License\LicenseInterface;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The license server writes the sentence a customer reads when a key is
 * refused; this plugin passes it through as written and adds its own words
 * in one place — an unknown key gains the note that the paste has already
 * been cleaned, so re-pasting it will not change the answer.
 */
final class LicenseSectionTest extends TestCase
{
    private const SERVER_SENTENCE = 'This key isn\'t one we issued — check it against your purchase email.';

    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('apply_filters')->alias(static fn (string $hook, mixed $value): mixed => $value);
        Functions\when('untrailingslashit')->alias(static fn (string $s): string => rtrim($s, '/'));
        Functions\when('__')->returnArg();
    }

    /** @return array<int, array{string}> */
    public static function serverVerdicts(): array
    {
        return [['expired'], ['revoked'], ['activation_limit_reached'], ['unknown_product'], ['http_error']];
    }

    #[DataProvider('serverVerdicts')]
    public function testTheServersSentencePassesThroughUnchanged(string $code): void
    {
        self::assertSame(
            self::SERVER_SENTENCE,
            $this->failureMessage(['success' => false, 'error' => self::SERVER_SENTENCE, 'error_code' => $code])
        );
    }

    public function testAnUnknownKeyKeepsTheServersSentenceAndGainsTheAlreadyStrippedHint(): void
    {
        $message = $this->failureMessage(['success' => false, 'error' => self::SERVER_SENTENCE, 'error_code' => 'invalid_key']);

        self::assertStringStartsWith(self::SERVER_SENTENCE, $message);
        self::assertStringContainsString('already stripped', $message);
    }

    public function testNoSentenceAtAllStillSaysSomething(): void
    {
        self::assertSame('Activation failed.', $this->failureMessage(['success' => false]));
    }

    /** The hint is only true because the normaliser really does strip what a paste brings along. */
    public function testThePastedKeyIsStrippedOfSpacesAndInvisibleCharacters(): void
    {
        $normalizeKey = (new \ReflectionMethod(LicenseSection::class, 'normalizeKey'))->getClosure($this->section());

        self::assertSame('FRSH-ABCD', $normalizeKey("\u{00A0}FRSH-\u{200B}ABCD\u{FEFF} "));
        self::assertSame('FRSH-ABCD', $normalizeKey('FRSH-ABCD'));
    }

    /** @param array{success?: bool, error?: string, error_code?: string} $response */
    private function failureMessage(array $response): string
    {
        return (new \ReflectionMethod(LicenseSection::class, 'failureMessage'))->getClosure($this->section())($response);
    }

    private function section(): LicenseSection
    {
        return new LicenseSection(new LicenseClient(), new class () implements LicenseInterface {
            public function isPro(): bool
            {
                return false;
            }

            public function canUseProxy(): bool
            {
                return false;
            }
        });
    }
}
