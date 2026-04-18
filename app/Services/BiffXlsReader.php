<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class BiffXlsReader
{
    private const END_OF_CHAIN = 0xFFFFFFFE;
    private const FREE_SECTOR = 0xFFFFFFFF;

    /**
     * @return array<string, array<int, array<int, string|int|float>>>
     */
    public function read(string $path): array
    {
        $bytes = file_get_contents($path);

        if ($bytes === false) {
            throw new RuntimeException('No se pudo leer el archivo Excel.');
        }

        $workbook = $this->extractWorkbookStream($bytes);
        $records = $this->readRecords($workbook);
        $sharedStrings = $this->readSharedStrings($records);
        $sheets = $this->readSheetDirectory($records);
        $result = [];

        foreach ($sheets as $sheet) {
            $result[$sheet['name']] = $this->readSheetCells($records, (int) $sheet['offset'], $sharedStrings);
        }

        return $result;
    }

    private function extractWorkbookStream(string $bytes): string
    {
        if (substr($bytes, 0, 8) !== hex2bin('D0CF11E0A1B11AE1')) {
            throw new RuntimeException('El archivo no parece ser un Excel .xls binario valido.');
        }

        $sectorSize = 1 << $this->u16($bytes, 30);
        $fatCount = $this->u32($bytes, 44);
        $firstDirectorySector = $this->u32($bytes, 48);
        $firstDifatSector = $this->u32($bytes, 68);
        $difatCount = $this->u32($bytes, 72);

        $fatSectors = [];

        for ($i = 0; $i < 109; $i++) {
            $sector = $this->u32($bytes, 76 + ($i * 4));

            if ($sector !== self::FREE_SECTOR && $sector !== self::END_OF_CHAIN) {
                $fatSectors[] = $sector;
            }
        }

        $sector = $firstDifatSector;

        for ($i = 0; $i < $difatCount; $i++) {
            if ($sector === self::FREE_SECTOR || $sector === self::END_OF_CHAIN) {
                break;
            }

            $sectorBytes = substr($bytes, $this->sectorOffset($sector, $sectorSize), $sectorSize);
            $entries = intdiv($sectorSize, 4) - 1;

            for ($j = 0; $j < $entries; $j++) {
                $fatSector = $this->u32($sectorBytes, $j * 4);

                if ($fatSector !== self::FREE_SECTOR && $fatSector !== self::END_OF_CHAIN) {
                    $fatSectors[] = $fatSector;
                }
            }

            $sector = $this->u32($sectorBytes, $sectorSize - 4);
        }

        $fat = [];

        foreach (array_slice($fatSectors, 0, $fatCount) as $fatSector) {
            $sectorBytes = substr($bytes, $this->sectorOffset($fatSector, $sectorSize), $sectorSize);
            $entries = intdiv($sectorSize, 4);

            for ($i = 0; $i < $entries; $i++) {
                $fat[] = $this->u32($sectorBytes, $i * 4);
            }
        }

        $directory = $this->readSectorChain($bytes, $fat, $firstDirectorySector, $sectorSize);

        for ($offset = 0; $offset + 128 <= strlen($directory); $offset += 128) {
            $entry = substr($directory, $offset, 128);
            $nameLength = $this->u16($entry, 64);

            if ($nameLength < 2) {
                continue;
            }

            $name = $this->decodeUtf16Le(substr($entry, 0, $nameLength - 2));

            if ($name !== 'Workbook' && $name !== 'Book') {
                continue;
            }

            $startSector = $this->u32($entry, 116);
            $size = $this->u64($entry, 120);

            return substr($this->readSectorChain($bytes, $fat, $startSector, $sectorSize), 0, $size);
        }

        throw new RuntimeException('No se encontro el stream Workbook dentro del archivo .xls.');
    }

    /**
     * @return array<int, array{offset: int, type: int, data: string}>
     */
    private function readRecords(string $workbook): array
    {
        $records = [];
        $offset = 0;
        $length = strlen($workbook);

        while ($offset + 4 <= $length) {
            $type = $this->u16($workbook, $offset);
            $recordLength = $this->u16($workbook, $offset + 2);
            $records[] = [
                'offset' => $offset,
                'type' => $type,
                'data' => substr($workbook, $offset + 4, $recordLength),
            ];
            $offset += 4 + $recordLength;
        }

        return $records;
    }

    /**
     * @param array<int, array{offset: int, type: int, data: string}> $records
     * @return array<int, string>
     */
    private function readSharedStrings(array $records): array
    {
        $strings = [];

        foreach ($records as $record) {
            if ($record['type'] !== 0x00FC) {
                continue;
            }

            $data = $record['data'];
            $uniqueCount = $this->u32($data, 4);
            $position = 8;

            for ($i = 0; $i < $uniqueCount && $position < strlen($data); $i++) {
                [$text, $position] = $this->decodeBiffString($data, $position);
                $strings[] = $text;
            }

            break;
        }

        return $strings;
    }

    /**
     * @param array<int, array{offset: int, type: int, data: string}> $records
     * @return array<int, array{name: string, offset: int}>
     */
    private function readSheetDirectory(array $records): array
    {
        $sheets = [];

        foreach ($records as $record) {
            if ($record['type'] !== 0x0085) {
                continue;
            }

            $data = $record['data'];
            $offset = $this->u32($data, 0);
            $nameLength = ord($data[6] ?? "\0");
            $flags = ord($data[7] ?? "\0");
            $rawName = substr($data, 8, $nameLength * (($flags & 1) ? 2 : 1));
            $name = ($flags & 1) ? $this->decodeUtf16Le($rawName) : $this->decodeLatin1($rawName);

            $sheets[] = ['name' => $name, 'offset' => $offset];
        }

        return $sheets;
    }

    /**
     * @param array<int, array{offset: int, type: int, data: string}> $records
     * @param array<int, string> $sharedStrings
     * @return array<int, array<int, string|int|float>>
     */
    private function readSheetCells(array $records, int $sheetOffset, array $sharedStrings): array
    {
        $startIndex = null;

        foreach ($records as $index => $record) {
            if ($record['offset'] === $sheetOffset) {
                $startIndex = $index;
                break;
            }
        }

        if ($startIndex === null) {
            return [];
        }

        $cells = [];
        $recordCount = count($records);

        for ($i = $startIndex; $i < $recordCount; $i++) {
            $record = $records[$i];

            if ($i !== $startIndex && ($record['type'] === 0x0809 || $record['type'] === 0x000A)) {
                break;
            }

            $data = $record['data'];

            switch ($record['type']) {
                case 0x00FD:
                    if (strlen($data) >= 10) {
                        $row = $this->u16($data, 0);
                        $column = $this->u16($data, 2);
                        $stringIndex = $this->u32($data, 6);
                        $cells[$row][$column] = $sharedStrings[$stringIndex] ?? '';
                    }
                    break;

                case 0x0203:
                    if (strlen($data) >= 14) {
                        $row = $this->u16($data, 0);
                        $column = $this->u16($data, 2);
                        $cells[$row][$column] = $this->normalizeNumber($this->f64($data, 6));
                    }
                    break;

                case 0x027E:
                    if (strlen($data) >= 10) {
                        $row = $this->u16($data, 0);
                        $column = $this->u16($data, 2);
                        $cells[$row][$column] = $this->normalizeNumber($this->decodeRk($this->u32($data, 6)));
                    }
                    break;

                case 0x00BD:
                    if (strlen($data) >= 6) {
                        $row = $this->u16($data, 0);
                        $firstColumn = $this->u16($data, 2);
                        $lastColumn = $this->u16($data, strlen($data) - 2);
                        $position = 4;
                        $column = $firstColumn;

                        while ($position + 6 <= strlen($data) - 2 && $column <= $lastColumn) {
                            $cells[$row][$column] = $this->normalizeNumber($this->decodeRk($this->u32($data, $position + 2)));
                            $position += 6;
                            $column++;
                        }
                    }
                    break;

                case 0x0204:
                    if (strlen($data) >= 8) {
                        $row = $this->u16($data, 0);
                        $column = $this->u16($data, 2);
                        $length = $this->u16($data, 6);
                        $cells[$row][$column] = $this->decodeLatin1(substr($data, 8, $length));
                    }
                    break;
            }
        }

        ksort($cells);

        return $cells;
    }

    /**
     * @param array<int, int> $fat
     */
    private function readSectorChain(string $bytes, array $fat, int $startSector, int $sectorSize): string
    {
        $chunks = [];
        $sector = $startSector;
        $seen = [];

        while (
            $sector !== self::END_OF_CHAIN
            && $sector !== self::FREE_SECTOR
            && isset($fat[$sector])
            && !isset($seen[$sector])
        ) {
            $seen[$sector] = true;
            $chunks[] = substr($bytes, $this->sectorOffset($sector, $sectorSize), $sectorSize);
            $sector = $fat[$sector];
        }

        return implode('', $chunks);
    }

    private function sectorOffset(int $sector, int $sectorSize): int
    {
        return 512 + ($sector * $sectorSize);
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function decodeBiffString(string $data, int $position): array
    {
        $charCount = $this->u16($data, $position);
        $position += 2;
        $flags = ord($data[$position] ?? "\0");
        $position++;

        $isUtf16 = (bool) ($flags & 1);
        $hasExtended = (bool) ($flags & 4);
        $hasRichText = (bool) ($flags & 8);
        $richTextRuns = 0;
        $extendedSize = 0;

        if ($hasRichText) {
            $richTextRuns = $this->u16($data, $position);
            $position += 2;
        }

        if ($hasExtended) {
            $extendedSize = $this->u32($data, $position);
            $position += 4;
        }

        $byteLength = $charCount * ($isUtf16 ? 2 : 1);
        $raw = substr($data, $position, $byteLength);
        $position += $byteLength + ($richTextRuns * 4) + $extendedSize;

        return [
            $isUtf16 ? $this->decodeUtf16Le($raw) : $this->decodeLatin1($raw),
            $position,
        ];
    }

    private function decodeRk(int $raw): float|int
    {
        $multiplier = ($raw & 0x02) ? 0.01 : 1.0;

        if ($raw & 0x01) {
            $value = $raw >> 2;

            if ($value & (1 << 29)) {
                $value -= 1 << 30;
            }

            return $this->normalizeNumber($value * $multiplier);
        }

        $binary = pack('V2', 0, $raw & 0xFFFFFFFC);

        return $this->normalizeNumber((float) unpack('d', $binary)[1] * $multiplier);
    }

    private function normalizeNumber(float $value): float|int
    {
        if (abs($value - round($value)) < 0.0000001) {
            return (int) round($value);
        }

        return $value;
    }

    private function decodeUtf16Le(string $value): string
    {
        return trim(mb_convert_encoding($value, 'UTF-8', 'UTF-16LE'));
    }

    private function decodeLatin1(string $value): string
    {
        return trim(mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1'));
    }

    private function u16(string $bytes, int $offset): int
    {
        return (int) unpack('v', substr($bytes, $offset, 2))[1];
    }

    private function u32(string $bytes, int $offset): int
    {
        $value = (int) unpack('V', substr($bytes, $offset, 4))[1];

        if ($value < 0) {
            $value += 4294967296;
        }

        return $value;
    }

    private function u64(string $bytes, int $offset): int
    {
        $parts = unpack('Vlow/Vhigh', substr($bytes, $offset, 8));

        return (int) ($parts['low'] + ($parts['high'] * 4294967296));
    }

    private function f64(string $bytes, int $offset): float
    {
        return (float) unpack('d', substr($bytes, $offset, 8))[1];
    }
}

