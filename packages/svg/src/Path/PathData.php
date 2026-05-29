<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Path;

/**
 * Parsed SVG path-data — the typed AST for a `<path d="…">` attribute.
 *
 * `PathData::parse()` is **lenient by design**: per SVG 2 §9.3.9 a malformed
 * `d` value is rendered up to (but not including) the segment containing the
 * error. We mirror that behaviour by accumulating commands until the
 * tokenizer can't make progress and then stopping silently. Callers that
 * want strict validation should walk the input themselves first.
 */
final class PathData
{
    /** @param list<PathCommand> $commands */
    public function __construct(public readonly array $commands) {}

    public static function parse(string $raw): self
    {
        $cmds = [];
        $offset = 0;
        $len = strlen($raw);
        $lastLetter = null;

        try {
            while ($offset < $len) {
                while ($offset < $len && (ctype_space($raw[$offset]) || $raw[$offset] === ',')) {
                    $offset++;
                }
                if ($offset >= $len) {
                    break;
                }
                $ch = $raw[$offset];

                if (self::isCommandLetter($ch)) {
                    $letter = $ch;
                    $offset++;
                    self::readCommand($raw, $offset, $letter, $cmds);
                    $lastLetter = match ($letter) {
                        'M' => 'L',
                        'm' => 'l',
                        default => $letter,
                    };
                    continue;
                }

                if ($lastLetter === null) {
                    // Number-before-any-command — malformed; stop here.
                    break;
                }

                // Implicit-repeat of the last command (with M→L / m→l
                // already applied above).
                self::readCommand($raw, $offset, $lastLetter, $cmds);
            }
        } catch (\InvalidArgumentException) {
            // Per spec: keep the commands accumulated before the error.
        }

        return new self($cmds);
    }

    private static function isCommandLetter(string $ch): bool
    {
        return match ($ch) {
            'M', 'm', 'L', 'l', 'H', 'h', 'V', 'v',
            'C', 'c', 'S', 's', 'Q', 'q', 'T', 't',
            'A', 'a', 'Z', 'z' => true,
            default => false,
        };
    }

    /**
     * @param list<PathCommand> $cmds
     */
    private static function readCommand(string $raw, int &$offset, string $letter, array &$cmds): void
    {
        $absolute = ctype_upper($letter);
        switch (strtolower($letter)) {
            case 'z':
                $cmds[] = new ClosePath($absolute);
                return;
            case 'm':
                $cmds[] = new MoveTo(
                    $absolute,
                    self::readNumber($raw, $offset),
                    self::readNumber($raw, $offset),
                );
                return;
            case 'l':
                $cmds[] = new LineTo(
                    $absolute,
                    self::readNumber($raw, $offset),
                    self::readNumber($raw, $offset),
                );
                return;
            case 'h':
                $cmds[] = new HorizontalLineTo($absolute, self::readNumber($raw, $offset));
                return;
            case 'v':
                $cmds[] = new VerticalLineTo($absolute, self::readNumber($raw, $offset));
                return;
            case 'c':
                $cmds[] = new CurveTo(
                    $absolute,
                    self::readNumber($raw, $offset),
                    self::readNumber($raw, $offset),
                    self::readNumber($raw, $offset),
                    self::readNumber($raw, $offset),
                    self::readNumber($raw, $offset),
                    self::readNumber($raw, $offset),
                );
                return;
            case 's':
                $cmds[] = new SmoothCurveTo(
                    $absolute,
                    self::readNumber($raw, $offset),
                    self::readNumber($raw, $offset),
                    self::readNumber($raw, $offset),
                    self::readNumber($raw, $offset),
                );
                return;
            case 'q':
                $cmds[] = new QuadraticCurveTo(
                    $absolute,
                    self::readNumber($raw, $offset),
                    self::readNumber($raw, $offset),
                    self::readNumber($raw, $offset),
                    self::readNumber($raw, $offset),
                );
                return;
            case 't':
                $cmds[] = new SmoothQuadraticCurveTo(
                    $absolute,
                    self::readNumber($raw, $offset),
                    self::readNumber($raw, $offset),
                );
                return;
            case 'a':
                $rx = self::readNumber($raw, $offset);
                $ry = self::readNumber($raw, $offset);
                $rot = self::readNumber($raw, $offset);
                $largeArc = self::readFlag($raw, $offset);
                $sweep = self::readFlag($raw, $offset);
                $x = self::readNumber($raw, $offset);
                $y = self::readNumber($raw, $offset);
                $cmds[] = new ArcTo($absolute, $rx, $ry, $rot, $largeArc, $sweep, $x, $y);
                return;
        }
    }

    private static function readNumber(string $raw, int &$offset): float
    {
        $len = strlen($raw);
        while ($offset < $len && (ctype_space($raw[$offset]) || $raw[$offset] === ',')) {
            $offset++;
        }
        if ($offset >= $len) {
            throw new \InvalidArgumentException('Unexpected end of path data; expected a number.');
        }
        if (preg_match('/\G[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?/', $raw, $m, 0, $offset) !== 1) {
            throw new \InvalidArgumentException(
                sprintf('Expected number at offset %d in path data.', $offset),
            );
        }
        $offset += strlen($m[0]);
        return (float) $m[0];
    }

    /**
     * Arc flag (SVG 2 §9.5.4) — a single binary digit, no separator required
     * from the previous or following token. `1` → true, `0` → false.
     */
    private static function readFlag(string $raw, int &$offset): bool
    {
        $len = strlen($raw);
        while ($offset < $len && (ctype_space($raw[$offset]) || $raw[$offset] === ',')) {
            $offset++;
        }
        if ($offset >= $len) {
            throw new \InvalidArgumentException('Unexpected end of path data; expected an arc flag.');
        }
        $ch = $raw[$offset];
        if ($ch !== '0' && $ch !== '1') {
            throw new \InvalidArgumentException(
                sprintf('Arc flag at offset %d must be 0 or 1.', $offset),
            );
        }
        $offset++;
        return $ch === '1';
    }
}
