<?php

namespace App\Services;

/**
 * Reads a TrueType font and turns text into SVG path data, so the text
 * renders the same everywhere without needing the font to be loaded.
 */
class FontOutline
{
    private string $data;

    /** @var array<string, int> */
    private array $tables = [];

    /** @var array<int, int> */
    private array $glyphByCode = [];

    private int $unitsPerEm;

    private bool $longLocaFormat;

    private int $numberOfHMetrics;

    public function __construct(string $fontPath)
    {
        $this->data = (string) file_get_contents($fontPath);

        $tableCount = $this->u16(4);

        for ($i = 0; $i < $tableCount; $i++) {
            $entry = 12 + 16 * $i;
            $this->tables[substr($this->data, $entry, 4)] = $this->u32($entry + 8);
        }

        $this->unitsPerEm = $this->u16($this->tables['head'] + 18);
        $this->longLocaFormat = $this->i16($this->tables['head'] + 50) === 1;
        $this->numberOfHMetrics = $this->u16($this->tables['hhea'] + 34);

        $this->readCmap();
    }

    /**
     * SVG path data for the text, starting at x on the baseline (y = 0).
     * The size is in the same units as the text's font size.
     */
    public function path(string $text, float $x, float $fontSize): string
    {
        $scale = $fontSize / $this->unitsPerEm;
        $pen = $x;
        $commands = '';

        foreach (mb_str_split($text) as $character) {
            $glyph = $this->glyphByCode[mb_ord($character)] ?? 0;

            foreach ($this->glyphContours($glyph) as $contour) {
                $commands .= $this->contourPath($contour, $pen, $scale);
            }

            $pen += $this->advanceWidth($glyph) * $scale;
        }

        return $commands;
    }

    private function readCmap(): void
    {
        $base = $this->tables['cmap'];
        $subtableCount = $this->u16($base + 2);
        $subtable = null;

        for ($i = 0; $i < $subtableCount; $i++) {
            $entry = $base + 4 + 8 * $i;
            $platform = $this->u16($entry);
            $encoding = $this->u16($entry + 2);
            $offset = $base + $this->u32($entry + 4);

            if ($this->u16($offset) !== 4) {
                continue;
            }

            if (($platform === 3 && $encoding === 1) || ($platform === 0 && $subtable === null)) {
                $subtable = $offset;
            }
        }

        if ($subtable === null) {
            return;
        }

        $segmentCount = intdiv($this->u16($subtable + 6), 2);
        $endCodes = $subtable + 14;
        $startCodes = $endCodes + 2 * $segmentCount + 2;
        $idDeltas = $startCodes + 2 * $segmentCount;
        $idRangeOffsets = $idDeltas + 2 * $segmentCount;

        for ($segment = 0; $segment < $segmentCount; $segment++) {
            $end = $this->u16($endCodes + 2 * $segment);
            $start = $this->u16($startCodes + 2 * $segment);
            $delta = $this->i16($idDeltas + 2 * $segment);
            $rangeOffset = $this->u16($idRangeOffsets + 2 * $segment);

            for ($code = $start; $code <= $end; $code++) {
                if ($code === 0xFFFF) {
                    continue;
                }

                if ($rangeOffset === 0) {
                    $glyph = ($code + $delta) & 0xFFFF;
                } else {
                    $glyph = $this->u16($idRangeOffsets + 2 * $segment + $rangeOffset + 2 * ($code - $start));

                    if ($glyph !== 0) {
                        $glyph = ($glyph + $delta) & 0xFFFF;
                    }
                }

                if ($glyph !== 0) {
                    $this->glyphByCode[$code] = $glyph;
                }
            }
        }
    }

    private function advanceWidth(int $glyph): int
    {
        return $this->u16($this->tables['hmtx'] + 4 * min($glyph, $this->numberOfHMetrics - 1));
    }

    /**
     * @return list<list<array{0: float, 1: float, 2: bool}>> contours of [x, y, onCurve] in font units
     */
    private function glyphContours(int $glyph, float $dx = 0, float $dy = 0): array
    {
        [$start, $end] = $this->glyphRange($glyph);

        if ($start === $end) {
            return [];
        }

        $base = $this->tables['glyf'] + $start;
        $contourCount = $this->i16($base);

        if ($contourCount < 0) {
            return $this->compositeContours($base + 10, $dx, $dy);
        }

        $endPoints = [];

        for ($i = 0; $i < $contourCount; $i++) {
            $endPoints[] = $this->u16($base + 10 + 2 * $i);
        }

        $pointCount = $endPoints === [] ? 0 : end($endPoints) + 1;
        $position = $base + 12 + 2 * $contourCount + $this->u16($base + 10 + 2 * $contourCount);

        $flags = [];

        while (count($flags) < $pointCount) {
            $flag = ord($this->data[$position++]);
            $flags[] = $flag;

            if ($flag & 8) {
                $repeat = ord($this->data[$position++]);

                for ($k = 0; $k < $repeat; $k++) {
                    $flags[] = $flag;
                }
            }
        }

        $xs = $this->readCoordinates($flags, $position, 2, 16);
        $ys = $this->readCoordinates($flags, $position, 4, 32);

        $contours = [];
        $first = 0;

        foreach ($endPoints as $last) {
            $contour = [];

            for ($i = $first; $i <= $last; $i++) {
                $contour[] = [$xs[$i] + $dx, $ys[$i] + $dy, (bool) ($flags[$i] & 1)];
            }

            $contours[] = $contour;
            $first = $last + 1;
        }

        return $contours;
    }

    /**
     * @param  list<int>  $flags
     * @return list<float>
     */
    private function readCoordinates(array $flags, int &$position, int $shortBit, int $sameBit): array
    {
        $values = [];
        $value = 0;

        foreach ($flags as $flag) {
            if ($flag & $shortBit) {
                $delta = ord($this->data[$position++]);

                if (! ($flag & $sameBit)) {
                    $delta = -$delta;
                }
            } elseif ($flag & $sameBit) {
                $delta = 0;
            } else {
                $delta = $this->i16($position);
                $position += 2;
            }

            $value += $delta;
            $values[] = (float) $value;
        }

        return $values;
    }

    /**
     * @return list<list<array{0: float, 1: float, 2: bool}>>
     */
    private function compositeContours(int $position, float $dx, float $dy): array
    {
        $contours = [];

        do {
            $flags = $this->u16($position);
            $glyph = $this->u16($position + 2);
            $position += 4;

            if ($flags & 1) {
                $offsetX = $this->i16($position);
                $offsetY = $this->i16($position + 2);
                $position += 4;
            } else {
                $offsetX = $this->signedByte($position);
                $offsetY = $this->signedByte($position + 1);
                $position += 2;
            }

            if ($flags & 8) {
                $position += 2;
            } elseif ($flags & 0x40) {
                $position += 4;
            } elseif ($flags & 0x80) {
                $position += 8;
            }

            if (! ($flags & 2)) {
                $offsetX = 0;
                $offsetY = 0;
            }

            array_push($contours, ...$this->glyphContours($glyph, $dx + $offsetX, $dy + $offsetY));
        } while ($flags & 0x20);

        return $contours;
    }

    /**
     * Converts one TrueType contour (quadratic curves) into SVG path commands.
     *
     * @param  list<array{0: float, 1: float, 2: bool}>  $contour
     */
    private function contourPath(array $contour, float $pen, float $scale): string
    {
        $count = count($contour);

        if ($count === 0) {
            return '';
        }

        $startIndex = null;

        foreach ($contour as $index => $point) {
            if ($point[2]) {
                $startIndex = $index;
                break;
            }
        }

        if ($startIndex === null) {
            $last = $contour[$count - 1];
            $first = $contour[0];
            $points = array_merge(
                [[($last[0] + $first[0]) / 2, ($last[1] + $first[1]) / 2, true]],
                $contour,
            );
        } else {
            $points = array_merge(array_slice($contour, $startIndex), array_slice($contour, 0, $startIndex));
        }

        $size = count($points);
        $toSvg = fn (array $point): string => sprintf(
            '%.3f %.3f',
            $pen + $point[0] * $scale,
            -$point[1] * $scale,
        );

        $path = 'M'.$toSvg($points[0]);

        for ($i = 1; $i <= $size; $i++) {
            $point = $points[$i % $size];

            if ($point[2]) {
                $path .= 'L'.$toSvg($point);

                continue;
            }

            $next = $points[($i + 1) % $size];

            if ($next[2]) {
                $path .= 'Q'.$toSvg($point).' '.$toSvg($next);
                $i++;

                continue;
            }

            $middle = [($point[0] + $next[0]) / 2, ($point[1] + $next[1]) / 2, true];
            $path .= 'Q'.$toSvg($point).' '.$toSvg($middle);
        }

        return $path.'Z';
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function glyphRange(int $glyph): array
    {
        $location = $this->tables['loca'];

        if ($this->longLocaFormat) {
            return [$this->u32($location + 4 * $glyph), $this->u32($location + 4 * ($glyph + 1))];
        }

        return [$this->u16($location + 2 * $glyph) * 2, $this->u16($location + 2 * ($glyph + 1)) * 2];
    }

    private function u16(int $offset): int
    {
        return (ord($this->data[$offset]) << 8) | ord($this->data[$offset + 1]);
    }

    private function i16(int $offset): int
    {
        $value = $this->u16($offset);

        return $value >= 0x8000 ? $value - 0x10000 : $value;
    }

    private function u32(int $offset): int
    {
        return ($this->u16($offset) << 16) | $this->u16($offset + 2);
    }

    private function signedByte(int $offset): int
    {
        $value = ord($this->data[$offset]);

        return $value >= 0x80 ? $value - 0x100 : $value;
    }
}
