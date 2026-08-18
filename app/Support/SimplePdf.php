<?php

namespace App\Support;

class SimplePdf
{
    private const PAGE_WIDTH = 612;

    private const PAGE_HEIGHT = 792;

    private const HEADER_HEIGHT = 64;

    /**
     * @param  list<string>  $lines
     */
    public function fromLines(array $lines, string $title = 'Report'): string
    {
        $pages = [];
        $current = [];
        $lineCount = 0;
        $maxLinesPerPage = 46;

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
        $logo = $this->logoImage();
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
        ];

        $nextId = 3;
        $fontId = $nextId++;
        $boldFontId = $nextId++;
        $objects[$fontId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[$boldFontId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        $imageId = null;

        if ($logo !== null) {
            $imageId = $nextId++;
            $objects[$imageId] = '<< /Type /XObject /Subtype /Image /Width '.$logo['width']
                .' /Height '.$logo['height']
                .' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '
                .strlen($logo['data'])." >>\nstream\n{$logo['data']}\nendstream";
        }

        $xobjects = $imageId === null ? '' : " /XObject << /Im1 {$imageId} 0 R >>";
        $kids = [];

        foreach ($pages as $index => $pageLines) {
            $contentId = $nextId++;
            $pageId = $nextId++;
            $kids[] = "{$pageId} 0 R";

            $stream = $this->pageContent(
                $pageLines,
                $title,
                $index + 1,
                count($pages),
                $logo,
            );
            $objects[$contentId] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream";
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::PAGE_WIDTH.' '.self::PAGE_HEIGHT
                ."] /Contents {$contentId} 0 R /Resources << /Font << /F1 {$fontId} 0 R /F2 {$boldFontId} 0 R >>{$xobjects} >> >>";
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($pages).' >>';

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
     * @param  array{width: int, height: int, data: string}|null  $logo
     */
    private function pageContent(
        array $lines,
        string $title,
        int $pageNumber,
        int $pageCount,
        ?array $logo,
    ): string {
        $headerBottom = self::PAGE_HEIGHT - self::HEADER_HEIGHT;
        $commands = [
            'q',
            '0 0 0 rg',
            '0 '.$headerBottom.' '.self::PAGE_WIDTH.' '.self::HEADER_HEIGHT.' re f',
            'Q',
        ];

        if ($logo !== null) {
            $logoHeight = 36;
            $logoWidth = $logoHeight * ($logo['width'] / $logo['height']);
            $logoY = $headerBottom + ((self::HEADER_HEIGHT - $logoHeight) / 2);

            $commands[] = 'q';
            $commands[] = sprintf('%.2f 0 0 %.2f 50 %.2f cm', $logoWidth, $logoHeight, $logoY);
            $commands[] = '/Im1 Do';
            $commands[] = 'Q';
        } else {
            $commands[] = 'BT';
            $commands[] = '1 1 1 rg';
            $commands[] = '/F2 16 Tf';
            $commands[] = '50 '.($headerBottom + 24).' Td';
            $commands[] = $this->text((string) config('app.name'));
            $commands[] = 'ET';
        }

        $commands[] = 'BT';
        $commands[] = '0 0 0 rg';
        $commands[] = '/F2 12 Tf';
        $commands[] = '50 '.($headerBottom - 28).' Td';
        $commands[] = $this->text($title);
        $commands[] = '/F1 9 Tf';
        $commands[] = '0 -16 Td';

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

    /**
     * @return array{width: int, height: int, data: string}|null
     */
    private function logoImage(): ?array
    {
        $path = public_path('img/logo.png');

        if (! extension_loaded('gd') || ! is_file($path)) {
            return null;
        }

        $source = @imagecreatefrompng($path);

        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $canvas = imagecreatetruecolor($width, $height);

        if ($canvas === false) {
            imagedestroy($source);

            return null;
        }

        $black = imagecolorallocate($canvas, 0, 0, 0);

        if ($black === false) {
            imagedestroy($canvas);
            imagedestroy($source);

            return null;
        }

        imagefilledrectangle($canvas, 0, 0, $width, $height, $black);
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
        imagedestroy($source);

        ob_start();
        imagejpeg($canvas, null, 90);
        $data = ob_get_clean();
        imagedestroy($canvas);

        if (! is_string($data) || $data === '') {
            return null;
        }

        return [
            'width' => $width,
            'height' => $height,
            'data' => $data,
        ];
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
