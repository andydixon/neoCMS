<?php

namespace NeoCMS;

/**
 * The one list of uploadable media: allowed extensions, their categories, and the safety checks applied to every upload.
 *
 * Only extensions listed here are accepted, so executables, scripts, archives, HTML/SVG and macro-enabled Office files are refused
 * by omission. On top of that, content is sniffed and scanned for embedded code. This is best-effort hardening, not a malware scanner.
 */
final class MediaTypes
{
    public const CATEGORIES = ['imagery' => 'Imagery', 'documents' => 'Documents', 'video' => 'Video', 'audio' => 'Audio'];

    /** extension => category */
    private const EXTENSIONS = [
        'jpg' => 'imagery', 'jpeg' => 'imagery', 'png' => 'imagery', 'gif' => 'imagery', 'webp' => 'imagery',
        'pdf' => 'documents', 'docx' => 'documents', 'xlsx' => 'documents', 'pptx' => 'documents',
        'odt' => 'documents', 'ods' => 'documents', 'odp' => 'documents', 'txt' => 'documents', 'csv' => 'documents',
        'mp4' => 'video', 'm4v' => 'video', 'webm' => 'video', 'mov' => 'video', 'ogv' => 'video',
        'mp3' => 'audio', 'wav' => 'audio', 'ogg' => 'audio', 'oga' => 'audio', 'm4a' => 'audio', 'aac' => 'audio', 'flac' => 'audio',
    ];

    /** Extensions that must never appear anywhere in a filename (for example report.exe.pdf), even though the file is renamed. */
    private const DENIED_PARTS = [
        'exe', 'dll', 'bat', 'cmd', 'com', 'msi', 'scr', 'ps1', 'vbs', 'vbe', 'js', 'jse', 'jar', 'wsf', 'hta', 'cpl', 'reg',
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'asp', 'aspx', 'jsp', 'jspx',
        'html', 'htm', 'xhtml', 'shtml', 'svg', 'xml', 'swf', 'lnk', 'apk', 'app', 'dmg', 'iso', 'so', 'bin',
        'docm', 'xlsm', 'pptm', 'dotm', 'xltm', 'potm', 'xlam', 'ppam',
    ];

    /** MIME types that always mean executable or markup content, whatever the extension claims. */
    private const BLOCKED_MIMES = [
        'application/x-dosexec', 'application/x-executable', 'application/x-sharedlib', 'application/x-mach-binary', 'application/x-msdownload',
        'application/x-elf', 'application/x-msi', 'application/java-archive', 'application/x-httpd-php', 'application/javascript',
        'text/javascript', 'text/x-php', 'text/x-shellscript', 'text/x-script.python', 'text/html', 'image/svg+xml', 'text/xml', 'application/xml',
    ];

    /** Case-insensitive markers of server-side or browser code, refused anywhere in any upload. Long enough never to occur by chance in compressed data. */
    private const CODE_MARKERS = ['<?php', '<script'];

    /** Three-byte markers also refused, but only in plain-text uploads: in compressed media they appear by chance in any file of a few megabytes. */
    private const TEXT_CODE_MARKERS = ['<?=', '<%@', '<%='];

    /** Entry names refused inside Office/OpenDocument packages. */
    private const DENIED_ENTRY_ENDINGS = ['.exe', '.dll', '.bat', '.cmd', '.com', '.msi', '.scr', '.ps1', '.vbs', '.js', '.jar', '.php', '.html', '.htm', '.sh', '.py', '.pl', '.bin'];

    /** Expected ODF mimetype entry per extension. */
    private const ODF = ['odt' => 'application/vnd.oasis.opendocument.text', 'ods' => 'application/vnd.oasis.opendocument.spreadsheet', 'odp' => 'application/vnd.oasis.opendocument.presentation'];

    /** Expected marker entry per OOXML extension. */
    private const OOXML = ['docx' => 'word/document.xml', 'xlsx' => 'xl/workbook.xml', 'pptx' => 'ppt/presentation.xml'];

    public static function extensions(): array
    {
        return array_keys(self::EXTENSIONS);
    }

    public static function categoryForExtension(string $extension): ?string
    {
        return self::EXTENSIONS[strtolower($extension)] ?? null;
    }

    /** Category of a stored filename, from its extension. */
    public static function categoryForName(string $name): ?string
    {
        return self::categoryForExtension(pathinfo($name, PATHINFO_EXTENSION));
    }

    /** Regular-expression fragment (unanchored) matching every stored media filename: legacy 32-hex names or slug-8hex names. */
    public static function nameRegex(): string
    {
        return '(?:[a-f0-9]{32}|[a-z0-9](?:[a-z0-9-]{0,78}[a-z0-9])?-[a-f0-9]{8})\.(?:' . implode('|', self::extensions()) . ')';
    }

    public static function isManagedName(string $name): bool
    {
        return preg_match('/^' . self::nameRegex() . '$/', $name) === 1;
    }

    /** New stored filename: a slug of the original name, a random suffix, and exactly one dot. */
    public static function storedName(string $originalName, string $extension): string
    {
        $base = pathinfo(basename($originalName), PATHINFO_FILENAME);
        if (function_exists('iconv')) {
            $base = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base) ?: $base;
        }
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($base)), '-');
        $slug = trim(substr($slug, 0, 60), '-');
        return ($slug !== '' ? $slug : 'file') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
    }

    /**
     * Check an uploaded file and report how to store it.
     *
     * @param array $limits maxBytes, maxWidth, maxHeight, maxPixels
     * @return array{ext: string, category: string, mime: string}
     * @throws \RuntimeException with a message safe to show the user
     */
    public static function inspect(string $path, string $originalName, array $limits = []): array
    {
        $parts = explode('.', strtolower(basename(str_replace('\\', '/', $originalName))));
        $extension = count($parts) > 1 ? (string) end($parts) : '';
        $category = self::EXTENSIONS[$extension] ?? null;
        foreach (array_slice($parts, 1) as $part) {
            if (in_array($part, self::DENIED_PARTS, true)) {
                throw new \RuntimeException('Files of this type (.' . $part . ') are not allowed.');
            }
        }
        if ($category === null) {
            throw new \RuntimeException('This file type is not allowed. Accepted: images, documents (PDF, Word, Excel, PowerPoint, OpenDocument, text, CSV), video, and audio.');
        }
        $size = @filesize($path);
        if ($size === false || $size < 1) {
            throw new \RuntimeException('The file is empty.');
        }
        if (isset($limits['maxBytes']) && $size > $limits['maxBytes']) {
            throw new \RuntimeException('This file is larger than the ' . round($limits['maxBytes'] / 1048576, 1) . ' MB limit.');
        }

        $head = (string) file_get_contents($path, false, null, 0, 8);
        foreach (["MZ", "\x7fELF", "#!", "\xCF\xFA\xED\xFE", "\xFE\xED\xFA\xCF", "\xCA\xFE\xBA\xBE"] as $signature) {
            if (str_starts_with($head, $signature)) {
                throw new \RuntimeException('Executable files are not allowed.');
            }
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = (string) finfo_file($finfo, $path);
        finfo_close($finfo);
        if (in_array($mime, self::BLOCKED_MIMES, true)) {
            throw new \RuntimeException('Executable or script content is not allowed.');
        }
        self::assertNoCode($path, $extension === 'txt' || $extension === 'csv');

        match (true) {
            $category === 'imagery' => self::checkImage($path, $extension, $mime, $limits),
            $extension === 'pdf' => self::checkPdf($path, $mime),
            isset(self::OOXML[$extension]) || isset(self::ODF[$extension]) => self::checkPackage($path, $extension),
            $extension === 'txt' || $extension === 'csv' => self::checkText($path, $mime),
            default => self::checkAv($path, $extension, $mime, $category),
        };
        return ['ext' => $extension === 'jpeg' ? 'jpg' : $extension, 'category' => $category, 'mime' => $mime];
    }

    private static function assertNoCode(string $path, bool $plainText): void
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('The file could not be read.');
        }
        $carry = '';
        while (!feof($handle)) {
            $chunk = (string) fread($handle, 1048576);
            $window = strtolower($carry . $chunk);
            foreach ($plainText ? array_merge(self::CODE_MARKERS, self::TEXT_CODE_MARKERS) : self::CODE_MARKERS as $marker) {
                if (str_contains($window, $marker)) {
                    fclose($handle);
                    throw new \RuntimeException('Files containing code are not allowed.');
                }
            }
            $carry = substr($chunk, -8);
        }
        fclose($handle);
    }

    private static function checkImage(string $path, string $extension, string $mime, array $limits): void
    {
        $expected = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'][$extension];
        if ($mime !== $expected) {
            throw new \RuntimeException('The file contents do not match its image type.');
        }
        $info = @getimagesize($path);
        if ($info === false) {
            throw new \RuntimeException('Uploaded file is not a valid image.');
        }
        [$width, $height] = $info;
        if ($width > ($limits['maxWidth'] ?? 8192) || $height > ($limits['maxHeight'] ?? 8192) || $width * $height > ($limits['maxPixels'] ?? 24 * 1024 * 1024)) {
            throw new \RuntimeException('Image dimensions exceed the configured limit.');
        }
    }

    private static function checkPdf(string $path, string $mime): void
    {
        $data = (string) file_get_contents($path);
        if ($mime !== 'application/pdf' || !str_contains(substr($data, 0, 1024), '%PDF-')) {
            throw new \RuntimeException('The file contents do not match a PDF.');
        }
        if (preg_match('#/JavaScript|/JS\b|/Launch|/EmbeddedFile|/RichMedia#', $data)) {
            throw new \RuntimeException('PDFs containing scripts, launch actions or embedded files are not allowed.');
        }
    }

    private static function checkText(string $path, string $mime): void
    {
        $data = (string) file_get_contents($path);
        if (str_contains($data, "\0") || !preg_match('//u', $data)) {
            throw new \RuntimeException('Text files must be plain UTF-8 text.');
        }
        if (!str_starts_with($mime, 'text/') && $mime !== 'application/csv') {
            throw new \RuntimeException('The file contents do not match a text file.');
        }
    }

    /** Audio and video must sniff as such; when libmagic only says "octet-stream", the container signature must match the extension. */
    private static function checkAv(string $path, string $extension, string $mime, string $category): void
    {
        if (str_starts_with($mime, 'audio/') || str_starts_with($mime, 'video/') || $mime === 'application/ogg') {
            return;
        }
        $head = (string) file_get_contents($path, false, null, 0, 12);
        $sync = strlen($head) > 1 && ord($head[0]) === 0xFF;
        $matches = match ($extension) {
            'mp4', 'm4v', 'mov', 'm4a' => in_array(substr($head, 4, 4), ['ftyp', 'moov', 'mdat', 'free', 'wide', 'skip', 'pnot'], true),
            'webm' => str_starts_with($head, "\x1A\x45\xDF\xA3"),
            'ogv', 'ogg', 'oga' => str_starts_with($head, 'OggS'),
            'mp3' => str_starts_with($head, 'ID3') || ($sync && (ord($head[1]) & 0xE0) === 0xE0),
            'aac' => $sync && (ord($head[1]) & 0xF0) === 0xF0,
            'wav' => str_starts_with($head, 'RIFF'),
            'flac' => str_starts_with($head, 'fLaC'),
            default => false,
        };
        if ($mime !== 'application/octet-stream' || !$matches) {
            throw new \RuntimeException('The file contents do not match ' . ($category === 'video' ? 'a video' : 'an audio') . ' file.');
        }
    }

    /** OOXML and OpenDocument files are ZIP packages: read the directory only, and refuse macros, embedded objects, and executables. */
    private static function checkPackage(string $path, string $extension): void
    {
        $entries = self::zipEntries($path);
        if (isset(self::ODF[$extension])) {
            $type = self::zipRead($path, $entries['mimetype'] ?? null);
            if ($type !== self::ODF[$extension]) {
                throw new \RuntimeException('The file contents do not match an OpenDocument file.');
            }
        } elseif (!isset($entries['[Content_Types].xml']) || !isset($entries[self::OOXML[$extension]])) {
            throw new \RuntimeException('The file contents do not match an Office document.');
        }
        foreach (array_keys($entries) as $name) {
            $lower = strtolower((string) $name);
            $bad = str_contains($lower, 'vbaproject') || str_contains($lower, 'activex') || str_contains($lower, 'embeddings/')
                || str_starts_with($lower, 'basic/') || str_starts_with($lower, 'scripts/') || str_contains($lower, '..') || str_starts_with($lower, '/');
            foreach (self::DENIED_ENTRY_ENDINGS as $ending) {
                $bad = $bad || str_ends_with($lower, $ending);
            }
            if ($bad) {
                throw new \RuntimeException('Documents containing macros, embedded objects or scripts are not allowed.');
            }
        }
    }

    /** @return array<string, array{offset: int, csize: int, usize: int, method: int}> */
    private static function zipEntries(string $path): array
    {
        $size = (int) filesize($path);
        $tail = (string) file_get_contents($path, false, null, max(0, $size - 65557));
        $eocd = strrpos($tail, "PK\x05\x06");
        if ($eocd === false || strlen($tail) < $eocd + 22 || !str_starts_with((string) file_get_contents($path, false, null, 0, 4), "PK\x03\x04")) {
            throw new \RuntimeException('The file is not a valid document package.');
        }
        $count = unpack('v', substr($tail, $eocd + 10, 2))[1];
        $directorySize = unpack('V', substr($tail, $eocd + 12, 4))[1];
        $directoryOffset = unpack('V', substr($tail, $eocd + 16, 4))[1];
        if ($count === 0xFFFF || $directorySize === 0xFFFFFFFF || $count > 5000 || $directorySize > 5 * 1048576) {
            throw new \RuntimeException('The document package is too complex.');
        }
        $directory = (string) file_get_contents($path, false, null, $directoryOffset, $directorySize);
        $entries = [];
        $position = 0;
        while ($position + 46 <= strlen($directory) && substr($directory, $position, 4) === "PK\x01\x02") {
            $method = unpack('v', substr($directory, $position + 10, 2))[1];
            $csize = unpack('V', substr($directory, $position + 20, 4))[1];
            $usize = unpack('V', substr($directory, $position + 24, 4))[1];
            [$nameLength, $extraLength, $commentLength] = array_values(unpack('v3', substr($directory, $position + 28, 6)));
            $offset = unpack('V', substr($directory, $position + 42, 4))[1];
            $entries[substr($directory, $position + 46, $nameLength)] = ['offset' => $offset, 'csize' => $csize, 'usize' => $usize, 'method' => $method];
            $position += 46 + $nameLength + $extraLength + $commentLength;
        }
        if (!$entries) {
            throw new \RuntimeException('The file is not a valid document package.');
        }
        return $entries;
    }

    /** Read one stored (uncompressed) entry, used only for the small ODF mimetype file. */
    private static function zipRead(string $path, ?array $entry): string
    {
        if ($entry === null || $entry['method'] !== 0 || $entry['usize'] > 256) {
            return '';
        }
        $header = (string) file_get_contents($path, false, null, $entry['offset'], 30);
        if (strlen($header) < 30) {
            return '';
        }
        [$nameLength, $extraLength] = array_values(unpack('v2', substr($header, 26, 4)));
        return (string) file_get_contents($path, false, null, $entry['offset'] + 30 + $nameLength + $extraLength, $entry['usize']);
    }
}
