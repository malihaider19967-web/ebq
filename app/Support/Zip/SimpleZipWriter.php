<?php

namespace App\Support\Zip;

/**
 * Minimal pure-PHP ZIP writer (stored / no compression). Used to package the
 * WordPress plugin for download without depending on the ext-zip PHP extension.
 *
 * Implements just enough of the ZIP file format (PKZip 2.0, method 0) to be
 * recognized by WordPress's plugin installer and the `unzip` CLI.
 */
class SimpleZipWriter
{
    /** @var list<array{name: string, crc: int, size: int, offset: int, dosTime: int}> */
    private array $entries = [];

    private string $data = '';

    public function addFile(string $archiveName, string $content): void
    {
        $crc = crc32($content);
        $size = strlen($content);
        $dosTime = $this->dosTime(time());
        $offset = strlen($this->data);

        $header = "\x50\x4b\x03\x04"         // local file header signature
            .pack('v', 20)                    // version needed
            .pack('v', 0)                     // general purpose flag
            .pack('v', 0)                     // compression method = stored
            .pack('V', $dosTime)              // last mod time+date
            .pack('V', $crc)
            .pack('V', $size)                 // compressed size
            .pack('V', $size)                 // uncompressed size
            .pack('v', strlen($archiveName))  // filename length
            .pack('v', 0)                     // extra field length
            .$archiveName
            .$content;

        $this->data .= $header;
        $this->entries[] = [
            'name' => $archiveName,
            'crc' => $crc,
            'size' => $size,
            'offset' => $offset,
            'dosTime' => $dosTime,
        ];
    }

    public function toBinary(): string
    {
        $cdStart = strlen($this->data);
        $cd = '';

        foreach ($this->entries as $e) {
            $cd .= "\x50\x4b\x01\x02"              // central directory signature
                .pack('v', 0x031E)                  // version made by (Unix, v3.0)
                .pack('v', 20)                      // version needed
                .pack('v', 0)                       // flags
                .pack('v', 0)                       // compression method
                .pack('V', $e['dosTime'])
                .pack('V', $e['crc'])
                .pack('V', $e['size'])
                .pack('V', $e['size'])
                .pack('v', strlen($e['name']))      // filename length
                .pack('v', 0)                       // extra length
                .pack('v', 0)                       // comment length
                .pack('v', 0)                       // disk #
                .pack('v', 0)                       // internal attrs
                .pack('V', 0x81A40000)               // external attrs (0644)
                .pack('V', $e['offset'])            // local header offset
                .$e['name'];
        }

        $count = count($this->entries);
        $eocd = "\x50\x4b\x05\x06"                  // end-of-central-directory
            .pack('v', 0)                            // disk number
            .pack('v', 0)                            // disk with central dir
            .pack('v', $count)                       // entries on this disk
            .pack('v', $count)                       // total entries
            .pack('V', strlen($cd))                  // size of central dir
            .pack('V', $cdStart)                     // central dir offset
            .pack('v', 0);                           // comment length

        return $this->data.$cd.$eocd;
    }

    private function dosTime(int $timestamp): int
    {
        $y = (int) date('Y', $timestamp);
        $m = (int) date('n', $timestamp);
        $d = (int) date('j', $timestamp);
        $h = (int) date('G', $timestamp);
        $mi = (int) date('i', $timestamp);
        $s = (int) date('s', $timestamp);

        $date = (($y - 1980) << 9) | ($m << 5) | $d;
        $time = ($h << 11) | ($mi << 5) | ($s >> 1);

        return ($date << 16) | $time;
    }
}
