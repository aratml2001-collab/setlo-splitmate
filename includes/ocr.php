<?php
// Receipt OCR: sends the photo to Gemini and turns its structured reply into line items + totals.
// OCR is assistive: every result goes through the Review screen before it is used.

declare(strict_types=1);

require_once __DIR__ . '/gemini.php';

// Sample receipt used by the "Load demo receipt" option (matches the README demo scenario).
// Works offline, as a backup for live demos. Item 2 is intentionally misread so the review/correction step can be demonstrated.
const DEMO_RECEIPT = [
    'items' => [
        ['name' => 'Chicken Inasal', 'qty' => 2, 'unit_price' => 179.00, 'needs_review' => false, 'suggestion' => null],
        ['name' => 'Banaus 5is1g',   'qty' => 1, 'unit_price' => 149.00, 'needs_review' => true,  'suggestion' => 'Bangus Sisig'],
        ['name' => 'Rice',           'qty' => 3, 'unit_price' => 45.00,  'needs_review' => false, 'suggestion' => null],
        ['name' => 'Halo-Halo',      'qty' => 2, 'unit_price' => 89.00,  'needs_review' => false, 'suggestion' => null],
        ['name' => 'Iced Tea',       'qty' => 4, 'unit_price' => 65.00,  'needs_review' => false, 'suggestion' => null],
        ['name' => 'Buko Pandan',    'qty' => 1, 'unit_price' => 70.00,  'needs_review' => false, 'suggestion' => null],
    ],
    'subtotal'       => 1150.00,
    'tax'            => 55.00,
    'service_charge' => 35.00,
    'discount'       => 0.0,
    'total'          => 1240.00,
    'raw_text'       => "MANG INASAL\nGuadalupe Branch\n--------------------------------\n2x Chicken Inasal 358.00\n1x Banaus 5is1g 149.00\n3x Rice 135.00\n2x Halo-Halo 178.00\n4x Iced Tea 260.00\n1x Buko Pandan 70.00\n--------------------------------\nSUBTOTAL 1,150.00\nTAX 55.00\nSVC CHARGE 35.00\nTOTAL 1,240.00\nCASH 1,500.00\nCHANGE 260.00",
];

const RECEIPT_PROMPT = <<<'TXT'
You are reading a photo of a restaurant or food receipt from the Philippines. Amounts are in pesos.

Return:
- raw_text: the receipt text transcribed line by line, top to bottom, starting with the store/restaurant name.
- items: every ordered food/drink line. name_as_printed is the item text exactly as printed (do not expand abbreviations,
  translate or tidy it). qty is the quantity (1 if none is printed). line_total is the amount printed for the whole line.
  Set unclear=true only when the name is smudged, cut off or ambiguous, and then give likely_name as your best reading;
  otherwise unclear=false and likely_name=null.
- subtotal, total: as printed, or null if missing.
- tax: VAT / tax amounts added to the bill (0 if none).
- service_charge: service charge amounts (0 if none).
- discount: senior citizen, PWD, promo discounts and "LESS VAT" amounts, as a positive number (0 if none).

Do not list payment and change lines (CASH, CHANGE, TENDER, CARD, GCASH, MAYA), VATABLE / VAT-EXEMPT / ZERO-RATED
breakdown lines, subtotals, taxes, service charges or discounts as items.
If the photo is not a receipt, return an empty items list.
TXT;

const RECEIPT_SCHEMA = [
    'type'       => 'OBJECT',
    'properties' => [
        'raw_text' => ['type' => 'STRING'],
        'items'    => [
            'type'  => 'ARRAY',
            'items' => [
                'type'       => 'OBJECT',
                'properties' => [
                    'name_as_printed' => ['type' => 'STRING'],
                    'qty'             => ['type' => 'INTEGER'],
                    'line_total'      => ['type' => 'NUMBER'],
                    'unclear'         => ['type' => 'BOOLEAN'],
                    'likely_name'     => ['type' => 'STRING', 'nullable' => true],
                ],
                'required'   => ['name_as_printed', 'qty', 'line_total', 'unclear'],
            ],
        ],
        'subtotal'       => ['type' => 'NUMBER', 'nullable' => true],
        'tax'            => ['type' => 'NUMBER'],
        'service_charge' => ['type' => 'NUMBER'],
        'discount'       => ['type' => 'NUMBER'],
        'total'          => ['type' => 'NUMBER', 'nullable' => true],
    ],
    'required'   => ['raw_text', 'items', 'tax', 'service_charge', 'discount'],
];

/**
 * Read a receipt photo into items and totals.
 * @return array{items: array, subtotal: ?float, tax: float, service_charge: float, discount: float, total: ?float, raw_text: string}
 * @throws GeminiException
 */
function scan_receipt(string $imagePath): array
{
    $data = gemini_json([gemini_image_part($imagePath), ['text' => RECEIPT_PROMPT]], RECEIPT_SCHEMA);

    $amount = fn ($v) => is_numeric($v) ? round(abs((float) $v), 2) : null;
    $items = [];
    foreach ($data['items'] ?? [] as $it) {
        $name = trim(preg_replace('/\s+/', ' ', (string) ($it['name_as_printed'] ?? '')));
        $lineTotal = $amount($it['line_total'] ?? null);
        if ($name === '' || !$lineTotal) {
            continue;
        }
        $qty = max(1, min(99, (int) ($it['qty'] ?? 1)));
        $likely = trim((string) ($it['likely_name'] ?? ''));
        $suggestion = $likely !== '' && strcasecmp($likely, $name) !== 0 ? mb_substr($likely, 0, 120) : null;
        $items[] = [
            'name'         => mb_substr($name, 0, 120),
            'qty'          => $qty,
            'unit_price'   => round($lineTotal / $qty, 2),
            'needs_review' => !empty($it['unclear']) || $suggestion !== null,
            'suggestion'   => $suggestion,
        ];
    }

    return [
        'items'          => $items,
        'subtotal'       => $amount($data['subtotal'] ?? null),
        'tax'            => $amount($data['tax'] ?? null) ?? 0.0,
        'service_charge' => $amount($data['service_charge'] ?? null) ?? 0.0,
        'discount'       => $amount($data['discount'] ?? null) ?? 0.0,
        'total'          => $amount($data['total'] ?? null),
        'raw_text'       => trim((string) ($data['raw_text'] ?? '')),
    ];
}
