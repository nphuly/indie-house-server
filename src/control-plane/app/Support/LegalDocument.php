<?php

namespace App\Support;

/**
 * The terms of use and privacy policy from lang/{vi,en}/legal.php, ready to
 * show: /terms and /privacy render it, and the apps get it as JSON.
 */
class LegalDocument
{
    public const DOCUMENTS = ['terms', 'privacy'];

    /**
     * @return array{
     *     document: string,
     *     title: string,
     *     updated: string,
     *     intro: string,
     *     sections: list<array{title: string, paragraphs: list<string>, items: list<array{label: ?string, text: string}>}>,
     *     contact: array{title: string, text: string, email: ?string},
     *     url: string,
     * }
     */
    public static function build(string $document): array
    {
        $instance = (string) config('instance.name');
        $text = fn (string $line): string => strtr($line, [':instance' => $instance]);
        $email = filter_var(config('instance.contact_email'), FILTER_VALIDATE_EMAIL) ?: null;

        return [
            'document' => $document,
            'title' => __("legal.{$document}.title"),
            'updated' => __('legal.updated'),
            'intro' => $text(__("legal.{$document}.intro")),
            'sections' => array_map(fn (array $section): array => [
                'title' => $section['title'],
                'paragraphs' => array_map($text, $section['paragraphs'] ?? []),
                'items' => array_map(fn (string|array $item): array => is_array($item)
                    ? ['label' => $item['label'], 'text' => $text($item['text'])]
                    : ['label' => null, 'text' => $text($item)], $section['items'] ?? []),
            ], __("legal.{$document}.sections")),
            'contact' => [
                'title' => __('legal.contact_title'),
                // With an email, the text ends where the address goes.
                'text' => $text(__($email ? 'legal.contact_email' : 'legal.contact_admin')),
                'email' => $email,
            ],
            'url' => route("legal.{$document}"),
        ];
    }
}
