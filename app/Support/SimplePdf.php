<?php

namespace App\Support;

class SimplePdf
{
    /**
     * @param  list<string>  $lines
     */
    public function fromLines(array $lines, string $title = 'Report'): string
    {
        $pages = [];
        $current = [];
        $lineCount = 0;
        $maxLinesPerPage = 52;

        foreach ($lines as $line) {
            foreach ($this->wrap($line, 95) as $wrappedLine) {
                if ($lineCount >= $maxLinesPerPage) {
                    $pages[] = $current;
                    $current = [];
                    $lineCount = 0;
                }

                $current[] = $wrappedLine;
                $lineCount++;
            }
        }

        if ($current !== [] || $pages === []) {
            $pages[] = $current;
        }

        return $this->build($pages, $title);
    }

    /**
     * @return list<string>
     */
    private function wrap(string $line, int $width): array
    {
        if ($line === '') {
            return [''];
        }

        return explode("\n", wordwrap($line, $width, "\n", true));
    }

    /**
     * @param  list<list<string>>  $pages
     */
    private function build(array $pages, string $title): string
    {
        $fontObjectId = 3 + (count($pages) * 2);
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
        ];

        $kids = [];
        $nextId = 3;

        foreach ($pages as $index => $pageLines) {
            $contentId = $nextId++;
            $pageId = $nextId++;
            $kids[] = "{$pageId} 0 R";

            $stream = $this->pageContent($pageLines, $title, $index + 1, count($pages));
            $objects[$contentId] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream";
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents {$contentId} 0 R /Resources << /Font << /F1 {$fontObjectId} 0 R >> >> >> >>";
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($pages).' >>';
        $objects[$fontObjectId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$object}\nendobj\n";
        }

        $xrefPosition = strlen($pdf);
        $size = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 {$size}\n";
        $pdf .= "0000000000 65535 f \n";

        for ($id = 1; $id < $size; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }

        $pdf .= "trailer << /Size {$size} /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefPosition}\n%%EOF";

        return $pdf;
    }

    /**
     * @param  list<string>  $lines
     */
    private function pageContent(array $lines, string $title, int $pageNumber, int $pageCount): string
    {
        $commands = [
            'BT',
            '/F1 11 Tf',
            '50 742 Td',
            $this->text($title),
            '/F1 9 Tf',
            '0 -18 Td',
        ];

        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $commands[] = '0 -13 Td';
            }

            $commands[] = $this->text($line);
        }

        $commands[] = 'ET';
        $commands[] = 'BT';
        $commands[] = '/F1 8 Tf';
        $commands[] = '50 36 Td';
        $commands[] = $this->text("Page {$pageNumber} of {$pageCount}");
        $commands[] = 'ET';

        return implode("\n", $commands);
    }

    private function text(string $value): string
    {
        $escaped = str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\\(', '\\)'],
            $this->toWinAnsi($value),
        );

        return "({$escaped}) Tj";
    }

    private function toWinAnsi(string $value): string
    {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);

        if (is_string($converted)) {
            return $converted;
        }

        return preg_replace('/[^\x20-\x7E]/', '?', $value) ?? '';
    }
}
