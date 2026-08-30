<?php

declare(strict_types = 1);

namespace App\Tests\ExceptionManagement;

use App\Tests\SystemKernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The translator itself, in isolation — see CategoryControllerTest /
 * ItemControllerTest for proof that API responses actually carry the
 * translated text (not just that the catalog resolves in a vacuum).
 */
class TranslationTest extends SystemKernelTestCase
{
    public function testExceptionKeyResolvesToPolishText(): void
    {
        $translator = self::getContainer()->get(TranslatorInterface::class);

        self::assertSame('Nie znaleziono kategorii.', $translator->trans('category.not_found', domain: 'exceptions'));
    }

    public function testValidatorKeyResolvesToPolishText(): void
    {
        $translator = self::getContainer()->get(TranslatorInterface::class);

        self::assertSame('To pole nie może być puste.', $translator->trans('not_blank', domain: 'validators'));
    }

    public function testExceptionKeyWithParametersSubstitutesThem(): void
    {
        $translator = self::getContainer()->get(TranslatorInterface::class);

        $message = $translator->trans('item.extension_not_allowed', ['%extension%' => 'exe'], domain: 'exceptions');

        self::assertStringContainsString('.exe', $message);
    }

    public function testUnknownKeyIsReturnedUnchanged(): void
    {
        // This is exactly what lets ExceptionSubscriber run every detail through
        // trans() uniformly — an already-final string (built at the throw site,
        // e.g. ItemService's duplicate-content message) just passes through.
        $translator = self::getContainer()->get(TranslatorInterface::class);

        $alreadyTranslated = 'Identyczna zawartość już istnieje jako item #7 ("foo.txt").';

        self::assertSame($alreadyTranslated, $translator->trans($alreadyTranslated, domain: 'exceptions'));
    }
}
