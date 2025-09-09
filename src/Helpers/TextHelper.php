<?php

namespace KraenzleRitter\Resources\Helpers;

class TextHelper
{
    /**
     * Extracts the first sentence from a text, considering common exceptions like date formats.
     *
     * @param string $text The text to process
     * @param int $maxLength Maximum length of the returned sentence (0 = unlimited)
     * @return string The first sentence
     */
    public static function extractFirstSentence($text, $maxLength = 0)
    {
        if (empty($text)) {
            return '';
        }

        // Normalize text: remove multiple spaces and strip HTML tags
        $text = static::stripWikiAndMarkdown($text);
        $text = strip_tags($text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/\[[^\]]*\]/', '', $text); // Remove [references], [citation needed], etc.
        $text = preg_replace('/^[^\]]*\]\s*/', '', $text); // Remove text before and including ] at the beginning
        // Keep parentheses for now as tests expect them
        $text = trim($text);

        $abbreviations = [
            // German
            'z\. ?B\.',
            'u\. ?a\.',
            'd\. ?h\.',
            'etc\.',
            'usw\.',
            'bspw\.',
            'sog\.',
            'bzw\.',
            'Dr\.',
            'Prof\.',
            'Dipl\.',
            'Nr\.',
            'Hr\.',
            'Fr\.',
            // English
            'Mr\.',
            'Mrs\.',
            'Ms\.',
            'Dr\.',
            'Prof\.',
            'Inc\.',
            'Ltd\.',
            'St\.',
            'e\.g\.',
            'i\.e\.',
            // French
            'M\.',
            'Mme\.',
            'Dr\.',
            'p\. ex\.',
            'etc\.',
            'n°\.',
            'env\.',
            'cf\.',
            'v\.',
            'St\.',
            // Italian
            'Sig\.',
            'Sig\.ra',
            'Dott\.',
            'Dr\.',
            'ecc\.',
            'p\.es\.',
            'es\.',
            'S\.r\.l\.',
            'n\.',
            // Years or date suffixes
            '\d{1,2}\.',  // Day numbers like "28."
            '\d{1,2}\.\s?(Jan|Feb|Mär|Apr|Mai|Jun|Jul|Aug|Sep|Okt|Nov|Dez|Januar|Februar|März|April|Juni|Juli|August|September|Oktober|November|Dezember)\.?',
            '\d{1,2}\s?(janv|févr|mars|avr|mai|juin|juil|août|sept|oct|nov|déc|janvier|février|mars|avril|mai|juin|juillet|août|septembre|octobre|novembre|décembre)\.?',
            '\d{1,2}\s?(gen|feb|mar|apr|mag|giu|lug|ago|set|ott|nov|dic|gennaio|febbraio|marzo|aprile|maggio|giugno|luglio|agosto|settembre|ottobre|novembre|dicembre)\.?'
        ];

        // Split text at potential sentence endings
        $parts = preg_split('/([.!?])(\s|$)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        $sentence = '';
        $foundSentenceEnd = false;
        for ($i = 0; $i < count($parts); $i += 3) {
            if (!isset($parts[$i])) break;

            $part = $parts[$i];
            $delimiter = $parts[$i + 1] ?? '';
            $space = $parts[$i + 2] ?? '';

            $sentence .= $part . $delimiter . $space;

            // Check if this part ends with an abbreviation
            $isAbbreviation = false;
            foreach ($abbreviations as $abbr) {
                if (preg_match('/\s' . $abbr . '$/', $part . $delimiter) ||
                    preg_match('/' . $abbr . '$/', $part . $delimiter)) {
                    $isAbbreviation = true;
                    break;
                }
            }

            // If it's not an abbreviation and we have a sentence delimiter, we found our sentence
            if (!$isAbbreviation && in_array($delimiter, ['.', '!', '?'])) {
                $foundSentenceEnd = true;
                break;
            }
        }

        $result = trim($sentence);

        // If no sentence ending was found, add period if needed
        if (!$foundSentenceEnd && !empty($result) && !in_array(substr($result, -1), ['.', '!', '?'])) {
            $result .= '.';
        }

        // Apply max length if specified
        if ($maxLength > 0 && strlen($result) > $maxLength) {
            // Cut to fit within maxLength including "..."
            $result = substr($result, 0, $maxLength - 2) . '...';
        }

        return $result;
    }

    public static function stripWikiAndMarkdown(string $input): string
    {
        // Remove Wikipedia-style links: [[Link]] or [[Link|Text]]
        $input = preg_replace('/\[\[(?:[^|\]]*\|)?([^\]]+)\]\]/', '$1', $input);

        // Remove Markdown-style links: [Text](URL)
        $input = preg_replace('/\[(.*?)\]\((.*?)\)/', '$1', $input);

        // Remove bold and italic formatting (**bold**, *italic*, __bold__, _italic_)
        $input = preg_replace('/(\*\*|__)(.*?)\1/', '$2', $input); // bold
        $input = preg_replace('/(\*|_)(.*?)\1/', '$2', $input);    // italic

        // Remove inline code: `code`
        $input = preg_replace('/`([^`]+)`/', '$1', $input);

        // Remove code blocks: ```...``` or indented blocks
        $input = preg_replace('/```.*?```/s', '', $input);
        $input = preg_replace('/^\s{4}.*$/m', '', $input);

        // Remove HTML tags
        $input = strip_tags($input);

        // Remove Markdown headers: # Header
        $input = preg_replace('/^#{1,6}\s*/m', '', $input);

        // Remove list markers: -, *, +, 1., etc.
        $input = preg_replace('/^\s*([-*+]|\d+\.)\s+/m', '', $input);

        // Remove Markdown table rows and separators
        $input = preg_replace('/^\s*\|.*\|\s*$/m', '', $input);

        // Decode HTML entities
        $input = html_entity_decode($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalize whitespace (multiple spaces, newlines)
        $input = preg_replace('/\s+/', ' ', $input);
        $input = trim($input);

        return $input;
    }
}
