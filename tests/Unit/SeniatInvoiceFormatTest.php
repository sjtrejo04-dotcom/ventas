<?php

declare(strict_types=1);

use App\Services\FiscalCalculationService;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

uses(TestCase::class);

/*
|--------------------------------------------------------------------------
| UNIT TESTS: SENIAT INVOICE FORMAT & THERMAL TICKET SPECIFICATION
|--------------------------------------------------------------------------
| Validar que la estructura de datos para el ticket térmico contenga los
| campos exigidos por la providencia del SENIAT:
| - Datos de Emisor (RIF, Nombre, Dirección, Teléfono)
| - N° Factura correlativo y consecutivo
| - N° Control fiscal
| - Serial fiscal de la máquina o sistema
| - Desglose de ítems con marcas (E) Exento y (G) Gravable
| - Base Imponible 16% y Alícuota IVA 16%
| - Base Imponible IGTF 3% y Monto IGTF 3% (cuando aplica)
| - Código QR fiscal con payload normativo SENIAT
| - Renderizado de la plantilla térmica (58mm y 80mm)
*/

dataset('seniatSampleTicketProvider', function () {
    $ticketSingleCurrencyBs = [
        'company_name' => 'SUPERMERCADOS & TIENDAS VENEZUELA C.A.',
        'company_rif' => 'J-50123456-7',
        'company_address' => 'Av. Francisco de Miranda, Centro Empresarial, Piso 1, Caracas',
        'company_phone' => '(0212) 555-0100',
        'invoice_number' => 'FACT-000042',
        'control_number' => '00-000042',
        'pos_document_number' => 'POS-000042',
        'fiscal_serial' => 'Z7C7028525',
        'date_time' => '06/09/2026 15:30:00',
        'cashier_name' => 'Santiago Pérez',
        'register_name' => 'Caja 01 Principal',
        'shift_id' => 1,
        'customer_name' => 'Consumidor Final',
        'customer_doc' => 'V-00000000',
        'customer_address' => 'Ciudad',
        'customer_phone' => '0000000000',
        'items' => [
            [
                'name' => 'Harina de Maíz Blanco 1kg',
                'tax_type' => '(E)',
                'quantity' => 2.0,
                'unit_price' => 1.50,
                'subtotal' => 3.00,
                'serial_number' => '',
                'warranty_days' => 0,
            ],
            [
                'name' => 'Monitor 24" LED Estándar',
                'tax_type' => '(G)',
                'quantity' => 1.0,
                'unit_price' => 100.00,
                'subtotal' => 100.00,
                'serial_number' => 'SN-MN-887711',
                'warranty_days' => 90,
            ],
        ],
        'subtotal' => 103.00,
        'exempt_amount' => 3.00,
        'taxable_base' => 100.00,
        'vat_amount' => 16.00,
        'igtf_base' => 0.00,
        'igtf_amount' => 0.00,
        'total_amount_usd' => 119.00,
        'total_amount_bs' => 5950.00,
        'bcv_rate' => 50.00,
        'change_due_usd' => 0.00,
        'change_due_bs' => 0.00,
        'payments' => [
            [
                'name' => 'Punto de Venta',
                'amount' => 5950.00,
                'currency' => 'VES',
                'reference' => 'LOTE-5566',
            ],
        ],
        'qr_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data='.urlencode('RIF:J501234567|FACT:FACT-000042|FECHA:2026-09-06|TOTAL_USD:119.00|TOTAL_BS:5950.00|IVA:16.00|IGTF:0.00'),
    ];

    $ticketMultiCurrencyUsd = [
        'company_name' => 'SUPERMERCADOS & TIENDAS VENEZUELA C.A.',
        'company_rif' => 'J-50123456-7',
        'company_address' => 'Av. Francisco de Miranda, Centro Empresarial, Piso 1, Caracas',
        'company_phone' => '(0212) 555-0100',
        'invoice_number' => 'FACT-000043',
        'control_number' => '00-000043',
        'pos_document_number' => 'POS-000043',
        'fiscal_serial' => 'Z7C7028525',
        'date_time' => '06/09/2026 16:45:00',
        'cashier_name' => 'María Rodríguez',
        'register_name' => 'Caja 01 Principal',
        'shift_id' => 2,
        'customer_name' => 'Inversiones Ávila C.A.',
        'customer_doc' => 'J-31234567-8',
        'customer_address' => 'Chacao, Caracas',
        'customer_phone' => '04141234567',
        'items' => [
            [
                'name' => 'Queso Blanco Pasteurizado 500g',
                'tax_type' => '(E)',
                'quantity' => 1.0,
                'unit_price' => 5.00,
                'subtotal' => 5.00,
                'serial_number' => '',
                'warranty_days' => 0,
            ],
            [
                'name' => 'Aceite Vegetal Comestible 1L',
                'tax_type' => '(G)',
                'quantity' => 2.0,
                'unit_price' => 10.00,
                'subtotal' => 20.00,
                'serial_number' => '',
                'warranty_days' => 0,
            ],
        ],
        'subtotal' => 25.00,
        'exempt_amount' => 5.00,
        'taxable_base' => 20.00,
        'vat_amount' => 3.20,
        'igtf_base' => 28.20,
        'igtf_amount' => 0.85,
        'total_amount_usd' => 29.05,
        'total_amount_bs' => 1452.50,
        'bcv_rate' => 50.00,
        'change_due_usd' => 0.95,
        'change_due_bs' => 47.50,
        'payments' => [
            [
                'name' => 'Efectivo Divisas ($)',
                'amount' => 30.00,
                'currency' => 'USD',
                'reference' => '',
            ],
        ],
        'qr_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data='.urlencode('RIF:J501234567|FACT:FACT-000043|FECHA:2026-09-06|TOTAL_USD:29.05|TOTAL_BS:1452.50|IVA:3.20|IGTF:0.85'),
    ];

    yield 'Single Currency Model (100% Bolívares - Sin IGTF)' => [$ticketSingleCurrencyBs, false];
    yield 'Multi Currency Model (Pago Efectivo Divisas USD - Con IGTF 3%)' => [$ticketMultiCurrencyUsd, true];
});

test('ticket data structure contains all mandatory SENIAT providencia fields', function (array $ticketData, bool $hasIgtf) {
    // 1. Legal Issuer Fields
    expect($ticketData)->toHaveKeys([
        'company_name',
        'company_rif',
        'company_address',
        'company_phone',
    ]);
    expect($ticketData['company_rif'])->toMatch('/^[JVEG]-\d{8,9}-\d$/');
    expect($ticketData['company_name'])->not->toBeEmpty();

    // 2. Fiscal Document Identification
    expect($ticketData)->toHaveKeys([
        'invoice_number',
        'control_number',
        'pos_document_number',
        'fiscal_serial',
        'date_time',
    ]);
    expect($ticketData['invoice_number'])->toStartWith('FACT-');
    expect($ticketData['control_number'])->toStartWith('00-');
    expect($ticketData['pos_document_number'])->toStartWith('POS-');
    expect($ticketData['fiscal_serial'])->not->toBeEmpty();
    expect($ticketData['date_time'])->toMatch('/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}:\d{2}$/');

    // 3. Register and Cashier Identification
    expect($ticketData)->toHaveKeys([
        'cashier_name',
        'register_name',
        'shift_id',
    ]);
    expect($ticketData['shift_id'])->toBeGreaterThan(0);

    // 4. Customer Identification
    expect($ticketData)->toHaveKeys([
        'customer_name',
        'customer_doc',
        'customer_address',
        'customer_phone',
    ]);
    expect($ticketData['customer_doc'])->toMatch('/^[VJEG]-[\d-]+$/');

    // 5. Fiscal Amounts Breakdown
    expect($ticketData)->toHaveKeys([
        'subtotal',
        'exempt_amount',
        'taxable_base',
        'vat_amount',
        'igtf_base',
        'igtf_amount',
        'total_amount_usd',
        'total_amount_bs',
        'bcv_rate',
        'change_due_usd',
        'change_due_bs',
    ]);

    // 6. Security and Payments
    expect($ticketData)->toHaveKeys([
        'items',
        'payments',
        'qr_url',
    ]);
    expect($ticketData['items'])->toBeArray()->not->toBeEmpty();
    expect($ticketData['payments'])->toBeArray()->not->toBeEmpty();
    expect($ticketData['qr_url'])->toStartWith('https://api.qrserver.com');
})->with('seniatSampleTicketProvider');

test('seniat item categorization strictly identifies exempt (E) and taxable (G) products', function (array $ticketData, bool $hasIgtf) {
    foreach ($ticketData['items'] as $item) {
        expect($item)->toHaveKeys([
            'name',
            'tax_type',
            'quantity',
            'unit_price',
            'subtotal',
            'serial_number',
            'warranty_days',
        ]);

        // Strict SENIAT fiscal mark: only '(E)' or '(G)' allowed
        expect($item['tax_type'])->toBeIn(['(E)', '(G)']);

        // Positive numeric calculations
        expect($item['quantity'])->toBeGreaterThan(0);
        expect($item['unit_price'])->toBeGreaterThan(0);
        expect($item['subtotal'])->toBe(round($item['quantity'] * $item['unit_price'], 2));
    }
})->with('seniatSampleTicketProvider');

test('seniat tax totals match items breakdown and fiscal calculation service rules', function (array $ticketData, bool $hasIgtf) {
    $calculatedSubtotal = 0.0;
    $calculatedExempt = 0.0;
    $calculatedTaxable = 0.0;

    foreach ($ticketData['items'] as $item) {
        $calculatedSubtotal += $item['subtotal'];
        if ($item['tax_type'] === '(E)') {
            $calculatedExempt += $item['subtotal'];
        } else {
            $calculatedTaxable += $item['subtotal'];
        }
    }

    expect(round($calculatedSubtotal, 2))->toBe($ticketData['subtotal'])
        ->and(round($calculatedExempt, 2))->toBe($ticketData['exempt_amount'])
        ->and(round($calculatedTaxable, 2))->toBe($ticketData['taxable_base']);

    // VAT 16% on taxable base
    $expectedVat = round($calculatedTaxable * FiscalCalculationService::VAT_RATE, 2);
    expect($ticketData['vat_amount'])->toBe($expectedVat);

    // Total in USD = Base + Exempt + VAT + IGTF
    $expectedTotalUsd = round($calculatedTaxable + $calculatedExempt + $expectedVat + $ticketData['igtf_amount'], 2);
    expect($ticketData['total_amount_usd'])->toBe($expectedTotalUsd);

    // Total in Bs = Total USD * BCV rate
    $expectedTotalBs = round($expectedTotalUsd * $ticketData['bcv_rate'], 2);
    expect($ticketData['total_amount_bs'])->toBe($expectedTotalBs);
})->with('seniatSampleTicketProvider');

test('seniat qr code url embeds required fiscal verification payload according to providencia', function (array $ticketData, bool $hasIgtf) {
    // Extract 'data' query param from QR URL
    $parsedUrl = parse_url($ticketData['qr_url']);
    expect($parsedUrl)->toHaveKey('query');

    parse_str($parsedUrl['query'], $queryParams);
    expect($queryParams)->toHaveKey('data');

    $payload = $queryParams['data'];

    // Required SENIAT pipe-delimited parameters: RIF, FACT, FECHA, TOTAL_USD, TOTAL_BS, IVA, IGTF
    expect($payload)
        ->toContain('RIF:')
        ->toContain('FACT:')
        ->toContain('FECHA:')
        ->toContain('TOTAL_USD:')
        ->toContain('TOTAL_BS:')
        ->toContain('IVA:')
        ->toContain('IGTF:');

    // Verify invoice number in QR matches the ticket's invoice number
    expect($payload)->toContain("FACT:{$ticketData['invoice_number']}");

    // Verify amounts in QR match ticket amounts
    expect($payload)
        ->toContain('TOTAL_USD:'.number_format($ticketData['total_amount_usd'], 2, '.', ''))
        ->toContain('TOTAL_BS:'.number_format($ticketData['total_amount_bs'], 2, '.', ''))
        ->toContain('IVA:'.number_format($ticketData['vat_amount'], 2, '.', ''))
        ->toContain('IGTF:'.number_format($ticketData['igtf_amount'], 2, '.', ''));
})->with('seniatSampleTicketProvider');

test('seniat thermal ticket blade template renders all legally required sections for 80mm and 58mm', function (array $ticketData, bool $hasIgtf) {
    foreach (['80mm', '58mm'] as $width) {
        $html = View::make('filament.admin.pages.pos.thermal-ticket', [
            'showTicketModal' => true,
            'ticketData' => $ticketData,
            'ticketWidth' => $width,
        ])->render();

        // 1. Header and Legal Issuer
        expect($html)->toContain(e($ticketData['company_name']));
        expect($html)->toContain("RIF: {$ticketData['company_rif']}");
        expect($html)->toContain(e($ticketData['company_address']));
        expect($html)->toContain($ticketData['company_phone']);

        // 2. Invoice Document Header
        expect($html)->toContain('SENIAT - FACTURA');
        expect($html)->toContain($ticketData['invoice_number']);
        expect($html)->toContain('N° CONTROL:');
        expect($html)->toContain($ticketData['control_number']);
        expect($html)->toContain('DOC POS:');
        expect($html)->toContain($ticketData['pos_document_number']);
        expect($html)->toContain($ticketData['date_time']);
        expect($html)->toContain($ticketData['register_name']);
        expect($html)->toContain("Turno #{$ticketData['shift_id']}");
        expect($html)->toContain(e($ticketData['cashier_name']));

        // 3. Customer Information
        expect($html)->toContain('CLIENTE:');
        expect($html)->toContain(e($ticketData['customer_name']));
        expect($html)->toContain('CI / RIF:');
        expect($html)->toContain($ticketData['customer_doc']);

        // 4. Items and SENIAT Tax Marks
        foreach ($ticketData['items'] as $item) {
            expect($html)->toContain(e($item['name']));
            expect($html)->toContain($item['tax_type']);
            expect($html)->toContain(number_format($item['quantity'], 2));
            expect($html)->toContain(number_format($item['unit_price'], 2));
            expect($html)->toContain(number_format($item['subtotal'], 2));

            if (! empty($item['serial_number'])) {
                expect($html)->toContain("SN: {$item['serial_number']}");
                expect($html)->toContain("Garantía: {$item['warranty_days']} días");
            }
        }

        // 5. Fiscal Breakdown (PlanSuárez Model)
        expect($html)->toContain('Base Imponible');
        expect($html)->toContain('G 16,00%');
        expect($html)->toContain('Alicuotas IVA');
        expect($html)->toContain('Monto A Pagar');
        expect($html)->toContain(number_format($ticketData['taxable_base'], 2));
        expect($html)->toContain(number_format($ticketData['vat_amount'], 2));
        expect($html)->toContain(number_format($ticketData['total_amount_usd'], 2));
        expect($html)->toContain(number_format($ticketData['total_amount_bs'], 2));
        expect($html)->toContain('Tasa Oficial BCV: '.number_format($ticketData['bcv_rate'], 2));

        if ($ticketData['exempt_amount'] > 0) {
            expect($html)->toContain('Monto Exento');
            expect($html)->toContain(number_format($ticketData['exempt_amount'], 2));
        }

        // 6. IGTF 3% Conditional Behavior
        if ($hasIgtf) {
            expect($html)->toContain('B.I./IGTF:');
            expect($html)->toContain(number_format($ticketData['igtf_base'], 2));
            expect($html)->toContain('IGTF 3,00%');
            expect($html)->toContain(number_format($ticketData['igtf_amount'], 2));
        }

        // 7. Security Elements & Fiscal Printer Serial
        expect($html)->toContain($ticketData['fiscal_serial']);
        expect($html)->toContain('CAMBIO MÁXIMO 2 DÍAS CON ESTA FACTURA');
        expect($html)->toContain(e($ticketData['qr_url']));
    }
})->with('seniatSampleTicketProvider');
