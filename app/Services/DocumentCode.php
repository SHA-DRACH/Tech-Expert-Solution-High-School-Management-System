<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use SimpleSoftwareIO\QrCode\Generator;

/**
 * The QR code printed on a receipt, an admission letter or a grade sheet.
 *
 * What it encodes is a **verification URL**, not the document's contents. A
 * receipt is a claim that money was paid; the code lets whoever is holding the
 * paper check that claim against the school's own records, which is the only
 * thing that makes a printed receipt hard to forge. Encoding the amount into
 * the code itself would just be the same unverifiable claim in a second
 * alphabet.
 *
 * The output is inline SVG, so it survives the "download and open with no
 * internet" case that the report card already relies on - a PNG would need
 * either a served URL or an image extension this deployment does not have.
 */
class DocumentCode
{
    /** Rendered at this many pixels square unless a caller says otherwise. */
    public const DEFAULT_SIZE = 110;

    /**
     * An SVG QR code for the given text, ready to drop into a page.
     *
     * Returns null rather than throwing if the code cannot be produced: a
     * receipt that prints without its QR code is still a usable receipt, and
     * failing the whole page over a decoration would be the wrong trade.
     */
    public function svg(string $text, int $size = self::DEFAULT_SIZE): ?string
    {
        if (trim($text) === '') {
            return null;
        }

        try {
            $svg = (string) (new Generator)
                ->size($size)
                ->margin(0)
                // High correction: these are printed, folded, and photographed
                // in poor light, and a stamp across a corner should not stop
                // the code scanning.
                ->errorCorrection('H')
                ->generate($text);

            // The generator emits its own XML declaration, which is invalid
            // partway through an HTML document.
            return preg_replace('/<\?xml[^>]*\?>\s*/', '', $svg);
        } catch (\Throwable $e) {
            Log::warning('Could not generate a QR code.', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * The verification URL for a document.
     *
     * Absolute, because the code is scanned from paper by a phone that has no
     * idea which host the document came from.
     */
    public function verifyUrl(string $type, string $reference): string
    {
        return route('verify.document', ['type' => $type, 'reference' => $reference]);
    }

    /** Convenience: the SVG for a document's verification URL. */
    public function forDocument(string $type, string $reference, int $size = self::DEFAULT_SIZE): ?string
    {
        return $this->svg($this->verifyUrl($type, $reference), $size);
    }
}
