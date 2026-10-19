<?php

namespace App\Services\Anki;

use App\Models\Flashcard;

/**
 * Turns flashcards into a text file Anki can import (File > Import).
 *
 * The format is the one described at https://docs.ankiweb.net/importing/text-files.html:
 * `#key:value` headers, then one note per line with tab-separated fields. Every card gets a
 * GUID made of its module and position, which survives re-seeding the database, so importing
 * the same file again updates the notes instead of duplicating them.
 */
class AnkiExport
{
    /** Where in the user's tag tree the cards are filed, with the module slug below it. */
    public const string TAG_ROOT = 'ruta-laravel';

    /**
     * @param  iterable<Flashcard>  $cards  With their `module` loaded.
     */
    public function build(iterable $cards): string
    {
        $lines = [
            '#separator:tab',
            '#html:true',
            '#guid column:1',
            '#tags column:4',
        ];

        foreach ($cards as $card) {
            $lines[] = implode("\t", [
                $this->guid($card),
                $this->field($card->question),
                $this->field($card->answer),
                self::TAG_ROOT.'::'.$card->module->slug,
            ]);
        }

        return implode("\n", $lines)."\n";
    }

    private function guid(Flashcard $card): string
    {
        return self::TAG_ROOT.'-'.$card->module->slug.'-'.$card->position;
    }

    /**
     * One field as HTML on a single line: the text is escaped, `code` between backticks
     * becomes <code>, and nothing is left that would be read as a separator or a quote.
     */
    private function field(string $text): string
    {
        $parts = explode('`', htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

        // Odd-numbered parts sit between backticks, the same convention the pages use.
        foreach ($parts as $index => $part) {
            if ($index % 2 === 1) {
                $parts[$index] = "<code>{$part}</code>";
            }
        }

        return str_replace(["\r\n", "\r", "\n", "\t"], ['<br>', '<br>', '<br>', ' '], implode('', $parts));
    }
}
