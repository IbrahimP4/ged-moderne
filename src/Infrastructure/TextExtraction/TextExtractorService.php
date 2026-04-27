<?php

declare(strict_types=1);

namespace App\Infrastructure\TextExtraction;

/**
 * Extrait le texte brut d'un fichier selon son type MIME.
 *
 * Supporte :
 *   - PDF          (smalot/pdfparser)
 *   - Word DOCX    (phpoffice/phpword)
 *   - Texte brut   (txt, md, csv, json, xml)
 *   - HTML         (strip_tags)
 *
 * Retourne null si le type MIME n'est pas supporté ou si l'extraction échoue.
 */
final class TextExtractorService
{
    /** Longueur max du texte indexé (économise de la place en DB) */
    private const MAX_CONTENT_LENGTH = 200_000;

    /** @var list<string> Types MIME considérés comme texte brut */
    private const PLAIN_TEXT_TYPES = [
        'text/plain', 'text/csv', 'text/markdown',
        'application/json', 'application/xml', 'text/xml',
        'text/html', 'text/css', 'text/javascript',
    ];

    public function extract(string $filePath, string $mimeType): ?string
    {
        if (!file_exists($filePath)) {
            return null;
        }

        try {
            $text = match (true) {
                $mimeType === 'application/pdf'                              => $this->extractPdf($filePath),
                $this->isWordDoc($mimeType)                                 => $this->extractWord($filePath),
                in_array($mimeType, self::PLAIN_TEXT_TYPES, true)           => $this->extractPlainText($filePath),
                str_starts_with($mimeType, 'text/')                         => $this->extractPlainText($filePath),
                default                                                     => null,
            };
        } catch (\Throwable) {
            // Ne jamais bloquer l'upload si l'extraction échoue
            return null;
        }

        if ($text === null || trim($text) === '') {
            return null;
        }

        // Nettoyage : normalisation Unicode, suppression des caractères de contrôle
        $text = $this->normalize($text);

        // Troncature pour ne pas stocker des textes gigantesques
        if (mb_strlen($text) > self::MAX_CONTENT_LENGTH) {
            $text = mb_substr($text, 0, self::MAX_CONTENT_LENGTH);
        }

        return $text;
    }

    /**
     * Retourne true si le type MIME est supporté pour l'extraction.
     */
    public function supports(string $mimeType): bool
    {
        return $mimeType === 'application/pdf'
            || $this->isWordDoc($mimeType)
            || in_array($mimeType, self::PLAIN_TEXT_TYPES, true)
            || str_starts_with($mimeType, 'text/');
    }

    // ── Extracteurs privés ────────────────────────────────────────────────────

    private function extractPdf(string $filePath): ?string
    {
        $parser   = new \Smalot\PdfParser\Parser();
        $pdf      = $parser->parseFile($filePath);
        $text     = $pdf->getText();

        return $text !== '' ? $text : null;
    }

    private function extractWord(string $filePath): ?string
    {
        $phpWord = \PhpOffice\PhpWord\IOFactory::load($filePath);
        $lines   = [];

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                    foreach ($element->getElements() as $child) {
                        if ($child instanceof \PhpOffice\PhpWord\Element\Text) {
                            $lines[] = $child->getText();
                        }
                    }
                } elseif ($element instanceof \PhpOffice\PhpWord\Element\Text) {
                    $lines[] = $element->getText();
                } elseif (method_exists($element, 'getText')) {
                    $lines[] = $element->getText();
                }
            }
        }

        $text = implode(' ', array_filter($lines));
        return $text !== '' ? $text : null;
    }

    private function extractPlainText(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        // HTML → texte brut
        if (str_contains($content, '<html') || str_contains($content, '<body')) {
            $content = strip_tags($content);
        }

        return $content;
    }

    private function isWordDoc(string $mimeType): bool
    {
        return in_array($mimeType, [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
            'application/msword',                                                       // .doc
            'application/vnd.oasis.opendocument.text',                                  // .odt
        ], true);
    }

    private function normalize(string $text): string
    {
        // Supprime les caractères de contrôle sauf \n \r \t
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $text) ?? $text;
        // Collapse des espaces multiples et des sauts de ligne excessifs
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
        return trim($text);
    }
}
