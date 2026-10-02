<?php

declare(strict_types=1);

namespace Odden\Service\Support;

/**
 * Makes untrusted text safe to put in a MailMessage line.
 *
 * Laravel renders notification lines as Markdown after Blade has HTML-escaped them, so HTML in
 * a value is already shown as typed, but Markdown is not: a ticket subject or contact name like
 * "[Reset](https://evil.example)" would become a real link in the email. escape() folds line
 * breaks into spaces (so a value can't start a heading, list, or quote) and backslash-escapes
 * the characters that start links, emphasis, code, and tables. HTML-significant characters are
 * left to Blade, since a backslash in front of an escaped entity would show the entity text.
 */
class MailMarkdown
{
    public static function escape(?string $text): string
    {
        $text = preg_replace('/\s*[\r\n]+\s*/', ' ', (string) $text) ?? '';

        return addcslashes($text, '\\`*_[]|~');
    }
}
