<?php

namespace App\Actions\Finance;

use Generator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class StoreEncryptedImportFile
{
    public const DISK = 'transaction_imports';

    public const MAGIC = "ILCSV\x01";

    private const CHUNK_BYTES = 65536;

    private const MAX_FRAME_BYTES = 262144;

    /**
     * Version one: magic, random 16-byte file ID, then length-prefixed Laravel
     * authenticated ciphertext frames. Each plaintext frame binds file ID,
     * monotonically increasing uint32 sequence, final flag, and original bytes.
     * A final empty frame is mandatory. Only ciphertext enters our temporary file.
     *
     * @param  resource  $source
     * @return array{sha256: string, size: int}
     */
    public function handle($source, string $path): array
    {
        $encrypted = tmpfile();
        if ($encrypted === false) {
            throw new RuntimeException('Encrypted import storage is unavailable.');
        }
        try {
            $fileId = random_bytes(16);
            $this->write($encrypted, self::MAGIC.$fileId);
            $sequence = 0;
            $size = 0;
            $hash = hash_init('sha256');
            while (! feof($source)) {
                $chunk = fread($source, self::CHUNK_BYTES);
                if ($chunk === false || ($chunk === '' && ! feof($source))) {
                    throw new RuntimeException('The uploaded CSV could not be read.');
                }
                if ($chunk === '') {
                    continue;
                }
                $size += strlen($chunk);
                if ($size > ParseCsvStatement::MAX_FILE_BYTES) {
                    throw new RuntimeException('The CSV exceeds the storage limit.');
                }
                hash_update($hash, $chunk);
                $frame = Crypt::encryptString($fileId.pack('N', $sequence++)."\x00".$chunk);
                $this->write($encrypted, pack('N', strlen($frame)).$frame);
            }
            $frame = Crypt::encryptString($fileId.pack('N', $sequence)."\x01");
            $this->write($encrypted, pack('N', strlen($frame)).$frame);
            rewind($encrypted);
            if (! Storage::disk(self::DISK)->put($path, $encrypted, ['visibility' => 'private'])) {
                throw new RuntimeException('Encrypted import storage is unavailable.');
            }

            return ['sha256' => hash_final($hash), 'size' => $size];
        } finally {
            fclose($encrypted);
        }
    }

    /**
     * Internal bounded reader; no download route is exposed. Consumers must finish
     * reading (and verify the final marker and batch content hash) before accepting
     * any staged operation. A different valid archive cannot replace the source.
     *
     * @param  resource  $encrypted
     * @return Generator<int, string>
     */
    public function read($encrypted, string $expectedSha256): Generator
    {
        if ($this->readBytes($encrypted, strlen(self::MAGIC)) !== self::MAGIC) {
            throw new RuntimeException('Invalid encrypted import file.');
        }
        $fileId = $this->readBytes($encrypted, 16);
        $sequence = 0;
        $total = 0;
        $hash = hash_init('sha256');
        while (true) {
            $frameHeader = unpack('Nsize', $this->readBytes($encrypted, 4));
            if ($frameHeader === false || ! is_int($frameHeader['size']) || $frameHeader['size'] < 1 || $frameHeader['size'] > self::MAX_FRAME_BYTES) {
                throw new RuntimeException('Invalid encrypted import frame.');
            }
            $length = $frameHeader['size'];
            $plain = Crypt::decryptString($this->readBytes($encrypted, $length));
            if (strlen($plain) < 21 || ! hash_equals($fileId, substr($plain, 0, 16))
                || substr($plain, 16, 4) !== pack('N', $sequence++)) {
                throw new RuntimeException('Invalid encrypted import sequence.');
            }
            $payload = substr($plain, 21);
            if ($plain[20] === "\x01") {
                if ($payload !== '' || fread($encrypted, 1) !== '') {
                    throw new RuntimeException('Invalid encrypted import ending.');
                }
                if (! hash_equals($expectedSha256, hash_final($hash))) {
                    throw new RuntimeException('Encrypted import content does not match its batch.');
                }

                return;
            }
            $total += strlen($payload);
            if ($plain[20] !== "\x00" || strlen($payload) > self::CHUNK_BYTES || $total > ParseCsvStatement::MAX_FILE_BYTES) {
                throw new RuntimeException('Invalid encrypted import frame.');
            }
            hash_update($hash, $payload);
            yield $payload;
        }
    }

    /** @param resource $stream */
    private function write($stream, string $bytes): void
    {
        $offset = 0;
        while ($offset < strlen($bytes)) {
            $written = fwrite($stream, substr($bytes, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Encrypted import storage is unavailable.');
            }
            $offset += $written;
        }
    }

    /** @param resource $stream */
    private function readBytes($stream, int $length): string
    {
        $bytes = '';
        $remaining = $length;
        while ($remaining > 0) {
            $chunk = fread($stream, $remaining);
            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('Truncated encrypted import file.');
            }
            $bytes .= $chunk;
            $remaining -= strlen($chunk);
        }

        return $bytes;
    }
}
