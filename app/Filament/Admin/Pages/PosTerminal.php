<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\DTOs\FiscalSummaryDTO;
use App\Models\CashRegister;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\CashShiftService;
use App\Services\FiscalCalculationService;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;
use UnitEnum;

class PosTerminal extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-computer-desktop';

    protected static ?string $navigationLabel = 'Punto de Venta (POS)';

    protected static ?string $title = '';

    protected static string|UnitEnum|null $navigationGroup = 'Ventas';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'pos';

    protected string $view = 'filament.admin.pages.pos-terminal';

    // --- Shift State ---
    public ?int $activeShiftId = null;

    public ?int $selectedRegisterId = null;

    public string $activeShiftRegisterName = 'Caja 01 Mostrador';

    public string $activeShiftUserName = '';

    protected ?CashShift $cachedActiveShift = null;

    protected ?Customer $cachedSelectedCustomer = null;

    protected ?Collection $cachedProducts = null;

    public float|string $openingCashBs = '';

    public float|string $openingCashUsd = '';

    public bool $showShiftOpenModal = false;

    public bool $showShiftCloseModal = false;

    public bool $showShiftReportModal = false;

    // Blind Arqueo counts
    public float|string $declaredCashBs = 0;

    public float|string $declaredCashUsd = 0;

    public float|string $declaredPosBs = 0;

    public float|string $declaredMobilePayBs = 0;

    public string $shiftNotes = '';

    public array $closedShiftReport = [];

    // --- BCV Rate ---
    public float $bcvRate = 50.0;

    // --- Product Catalog & Search ---
    public string $searchQuery = '';

    public string $selectedCategory = 'all'; // 'all', 'gravado', 'exento', 'en_stock'

    // --- Cart State ---
    /**
     * @var array<int, array<string, mixed>>
     */
    public array $cart = [];

    // Item Serial / Warranty Modal State
    public ?int $editingSerialProductId = null;

    public string $itemSerialNumber = '';

    public int $itemWarrantyDays = 0;

    public bool $showItemSerialModal = false;

    // --- Customer State ---
    public ?int $selectedCustomerId = null;

    public string $selectedCustomerName = 'Consumidor Final';

    public string $selectedCustomerDoc = 'V-00000000';

    public string $customerDocType = 'V';

    public string $customerDocNumber = '';

    public string $customerName = '';

    public string $customerPhone = '';

    public string $customerAddress = '';

    public bool $showCustomerModal = false;

    public string $customerSearchQuery = '';

    // --- Payment Wizard State ---
    public bool $showPaymentModal = false;

    public string $selectedPaymentMethod = 'cash_usd';

    public float|string $paymentAmount = '';

    public string $paymentReference = '';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $payments = [];

    // --- Post-Sale Ticket State ---
    public bool $showTicketModal = false;

    public array $ticketData = [];

    public string $ticketWidth = '80mm'; // '80mm' or '58mm'

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Punto de Venta (POS)';
    }

    public function mount(): void
    {
        $this->refreshBcvRate();
        $this->checkShiftStatus();
        $this->ensureDefaultCustomer();
    }

    public function refreshBcvRate(): void
    {
        $rateRecord = ExchangeRate::where('currency', 'USD')
            ->latest('date_published')
            ->latest('id')
            ->first();

        if ($rateRecord && (float) $rateRecord->rate > 0) {
            $this->bcvRate = (float) $rateRecord->rate;
        } else {
            $this->bcvRate = 50.0;
        }
    }

    public function checkShiftStatus(): void
    {
        $userId = Auth::id() ?? 1;
        $shiftService = app(CashShiftService::class);
        $activeShift = $shiftService->getActiveShiftForUser($userId);

        if ($activeShift) {
            $this->activeShiftId = $activeShift->id;
            $this->selectedRegisterId = $activeShift->cash_register_id;
            $this->activeShiftRegisterName = $activeShift->cashRegister->name ?? 'Caja 01 Mostrador';
            $this->activeShiftUserName = $activeShift->user->name ?? 'Cajero';
            $this->cachedActiveShift = $activeShift;
            $this->showShiftOpenModal = false;
        } else {
            $this->activeShiftId = null;
            $this->showShiftOpenModal = true;
            $firstRegister = CashRegister::where('is_active', true)->first();
            $this->selectedRegisterId = $firstRegister?->id;
        }
    }

    public function getActiveShiftProperty(): ?CashShift
    {
        if (! $this->activeShiftId) {
            return null;
        }

        return $this->cachedActiveShift ??= CashShift::with(['cashRegister', 'user'])->find($this->activeShiftId);
    }

    public function getActiveShift(): ?CashShift
    {
        return $this->activeShift;
    }

    // =========================================================================
    // SHIFT ACTIONS (Tarea 5)
    // =========================================================================

    public function openShift(): void
    {
        $userId = Auth::id() ?? 1;

        if (! $this->selectedRegisterId) {
            Notification::make()
                ->title('Error')
                ->body('Debe seleccionar una caja física para aperturar turno.')
                ->danger()
                ->send();

            return;
        }

        $openingBs = (float) $this->openingCashBs;
        $openingUsd = (float) $this->openingCashUsd;

        if ($openingBs < 0 || $openingUsd < 0) {
            Notification::make()
                ->title('Monto inválido')
                ->body('Los fondos de apertura no pueden ser negativos.')
                ->warning()
                ->send();

            return;
        }

        try {
            $shiftService = app(CashShiftService::class);
            $shift = $shiftService->openShift(
                cashRegisterId: (int) $this->selectedRegisterId,
                userId: $userId,
                openingBs: $openingBs,
                openingUsd: $openingUsd
            );

            $this->activeShiftId = $shift->id;
            $this->activeShiftRegisterName = $shift->cashRegister->name ?? 'Caja 01 Mostrador';
            $this->activeShiftUserName = $shift->user->name ?? 'Cajero';
            $this->cachedActiveShift = $shift;
            $this->showShiftOpenModal = false;
            $this->openingCashBs = '';
            $this->openingCashUsd = '';

            Notification::make()
                ->title('Turno Abierto Exitosamente')
                ->body("Turno #{$shift->id} iniciado en la caja {$shift->cashRegister->name}.")
                ->success()
                ->send();
        } catch (DomainException $e) {
            Notification::make()
                ->title('No se pudo abrir el turno')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Error inesperado')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function openCloseShiftModal(): void
    {
        $shift = $this->getActiveShift();
        if (! $shift) {
            Notification::make()
                ->title('Atención')
                ->body('No hay un turno abierto actualmente.')
                ->warning()
                ->send();

            return;
        }

        $this->declaredCashBs = 0;
        $this->declaredCashUsd = 0;
        $this->declaredPosBs = 0;
        $this->declaredMobilePayBs = 0;
        $this->shiftNotes = '';
        $this->showShiftCloseModal = true;
    }

    public function closeShift(): void
    {
        $shift = $this->getActiveShift();
        if (! $shift) {
            Notification::make()
                ->title('Error')
                ->body('No hay un turno activo para cerrar.')
                ->danger()
                ->send();

            return;
        }

        try {
            $shiftService = app(CashShiftService::class);
            $closedShift = $shiftService->closeShift($shift, [
                'declared_cash_bs' => (float) $this->declaredCashBs,
                'declared_cash_usd' => (float) $this->declaredCashUsd,
                'declared_pos_bs' => (float) $this->declaredPosBs,
                'declared_mobile_pay_bs' => (float) $this->declaredMobilePayBs,
                'notes' => $this->shiftNotes,
            ]);

            $this->closedShiftReport = [
                'id' => $closedShift->id,
                'cashier' => $closedShift->user->name ?? 'Cajero',
                'register' => $closedShift->cashRegister->name ?? 'Caja',
                'register_code' => $closedShift->cashRegister->code ?? '',
                'opened_at' => $closedShift->opened_at?->format('d/m/Y H:i:s'),
                'closed_at' => $closedShift->closed_at?->format('d/m/Y H:i:s'),
                'opening_cash_bs' => (float) $closedShift->opening_cash_bs,
                'opening_cash_usd' => (float) $closedShift->opening_cash_usd,
                'system_cash_bs' => (float) $closedShift->system_cash_bs,
                'system_cash_usd' => (float) $closedShift->system_cash_usd,
                'system_pos_bs' => (float) $closedShift->system_pos_bs,
                'system_mobile_pay_bs' => (float) $closedShift->system_mobile_pay_bs,
                'system_cashea_bs' => (float) $closedShift->system_cashea_bs,
                'declared_cash_bs' => (float) $closedShift->declared_cash_bs,
                'declared_cash_usd' => (float) $closedShift->declared_cash_usd,
                'declared_pos_bs' => (float) $closedShift->declared_pos_bs,
                'declared_mobile_pay_bs' => (float) $closedShift->declared_mobile_pay_bs,
                'difference_cash_bs' => (float) $closedShift->difference_cash_bs,
                'difference_cash_usd' => (float) $closedShift->difference_cash_usd,
                'notes' => $closedShift->notes,
            ];

            $this->activeShiftId = null;
            $this->showShiftCloseModal = false;
            $this->showShiftReportModal = true;

            Notification::make()
                ->title('Turno Cerrado Correctamente')
                ->body("Arqueo ciego completado para el Turno #{$closedShift->id}.")
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Error al cerrar turno')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function dismissShiftReportModal(): void
    {
        $this->showShiftReportModal = false;
        $this->showShiftOpenModal = true;
    }

    // =========================================================================
    // CATALOG & CART ACTIONS (Tarea 4)
    // =========================================================================

    public function getProductsProperty(): Collection
    {
        if ($this->cachedProducts !== null) {
            return $this->cachedProducts;
        }

        $query = Product::query();

        if ($this->searchQuery !== '') {
            $term = trim($this->searchQuery);
            $query->where(function ($q) use ($term) {
                $q->where('name', 'ilike', "%{$term}%")
                    ->orWhere('id', is_numeric($term) ? (int) $term : 0);
            });
        }

        if ($this->selectedCategory === 'gravado') {
            $query->where('has_vat', true);
        } elseif ($this->selectedCategory === 'exento') {
            $query->where('has_vat', false);
        } elseif ($this->selectedCategory === 'en_stock') {
            $query->where('stock', '>', 0);
        }

        return $this->cachedProducts = $query->orderBy('name')->take(50)->get();
    }

    public function searchBarcodeOrFirst(): void
    {
        if (trim($this->searchQuery) === '') {
            return;
        }

        $term = trim($this->searchQuery);
        $matches = Product::where('name', 'ilike', "%{$term}%")
            ->orWhere('id', is_numeric($term) ? (int) $term : 0)
            ->get();

        if ($matches->count() === 1) {
            $this->addToCart($matches->first()->id);
            $this->searchQuery = '';
        }
    }

    public function addToCart(int $productId, float $quantity = 1.0): void
    {
        if (! $this->activeShiftId) {
            Notification::make()
                ->title('Turno Requerido')
                ->body('Debe aperturar un turno de caja antes de agregar productos.')
                ->warning()
                ->send();
            $this->showShiftOpenModal = true;

            return;
        }

        $product = $this->products->firstWhere('id', $productId) ?? Product::find($productId);
        if (! $product) {
            return;
        }

        if (isset($this->cart[$productId])) {
            $this->cart[$productId]['quantity'] += $quantity;
            $this->cart[$productId]['subtotal'] = round($this->cart[$productId]['quantity'] * $this->cart[$productId]['unit_price'], 2);
        } else {
            $this->cart[$productId] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'unit_price' => (float) $product->price,
                'quantity' => $quantity,
                'stock' => (float) $product->stock,
                'has_vat' => (bool) $product->has_vat,
                'subtotal' => round($quantity * (float) $product->price, 2),
                'serial_number' => '',
                'warranty_days' => 0,
            ];
        }

        // Auto-recalculate payments if already in wizard
        $this->recalculatePaymentsToFit();
    }

    public function incrementQuantity(int $productId, float $step = 1.0): void
    {
        if (isset($this->cart[$productId])) {
            $this->cart[$productId]['quantity'] += $step;
            $this->cart[$productId]['subtotal'] = round($this->cart[$productId]['quantity'] * $this->cart[$productId]['unit_price'], 2);
            $this->recalculatePaymentsToFit();
        }
    }

    public function decrementQuantity(int $productId, float $step = 1.0): void
    {
        if (isset($this->cart[$productId])) {
            if ($this->cart[$productId]['quantity'] > $step) {
                $this->cart[$productId]['quantity'] -= $step;
                $this->cart[$productId]['subtotal'] = round($this->cart[$productId]['quantity'] * $this->cart[$productId]['unit_price'], 2);
            } else {
                unset($this->cart[$productId]);
            }
            $this->recalculatePaymentsToFit();
        }
    }

    public function updateQuantity(int $productId, mixed $quantity): void
    {
        $qty = (float) $quantity;
        if ($qty <= 0) {
            unset($this->cart[$productId]);
        } elseif (isset($this->cart[$productId])) {
            $this->cart[$productId]['quantity'] = $qty;
            $this->cart[$productId]['subtotal'] = round($qty * $this->cart[$productId]['unit_price'], 2);
        }
        $this->recalculatePaymentsToFit();
    }

    public function removeFromCart(int $productId): void
    {
        unset($this->cart[$productId]);
        $this->recalculatePaymentsToFit();
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->payments = [];
    }

    public function openItemSerialModal(int $productId): void
    {
        if (! isset($this->cart[$productId])) {
            return;
        }

        $this->editingSerialProductId = $productId;
        $this->itemSerialNumber = (string) ($this->cart[$productId]['serial_number'] ?? '');
        $this->itemWarrantyDays = (int) ($this->cart[$productId]['warranty_days'] ?? 0);
        $this->showItemSerialModal = true;
    }

    public function saveItemSerial(): void
    {
        if ($this->editingSerialProductId && isset($this->cart[$this->editingSerialProductId])) {
            $this->cart[$this->editingSerialProductId]['serial_number'] = trim($this->itemSerialNumber);
            $this->cart[$this->editingSerialProductId]['warranty_days'] = max(0, $this->itemWarrantyDays);
        }

        $this->showItemSerialModal = false;
        $this->editingSerialProductId = null;
    }

    // =========================================================================
    // CUSTOMER MANAGEMENT
    // =========================================================================

    public function ensureDefaultCustomer(): void
    {
        if ($this->selectedCustomerId) {
            return;
        }

        $default = Customer::where('document_number', '00000000')->first();
        if ($default) {
            $this->selectedCustomerId = $default->id;
            $this->selectedCustomerName = $default->name;
            $this->selectedCustomerDoc = $default->document_type.'-'.$default->document_number;
        }
    }

    public function getSelectedCustomerProperty(): ?Customer
    {
        if (! $this->selectedCustomerId) {
            return null;
        }

        return Customer::find($this->selectedCustomerId);
    }

    public function getSelectedCustomer(): ?Customer
    {
        return $this->selectedCustomer;
    }

    public function setConsumidorFinal(): void
    {
        $default = Customer::firstOrCreate(
            ['document_number' => '00000000'],
            [
                'document_type' => 'V',
                'name' => 'Consumidor Final',
                'address' => 'Ciudad',
                'phone' => '0000000000',
            ]
        );

        $this->selectedCustomerId = $default->id;
        $this->selectedCustomerName = $default->name;
        $this->selectedCustomerDoc = $default->document_type.'-'.$default->document_number;
        $this->showCustomerModal = false;
    }

    public function openCustomerModal(): void
    {
        $this->customerSearchQuery = '';
        $this->customerDocType = 'V';
        $this->customerDocNumber = '';
        $this->customerName = '';
        $this->customerPhone = '';
        $this->customerAddress = '';
        $this->showCustomerModal = true;
    }

    public function selectCustomer(int $id): void
    {
        $this->selectedCustomerId = $id;
        $customer = Customer::find($id);
        if ($customer) {
            $this->selectedCustomerName = $customer->name;
            $this->selectedCustomerDoc = $customer->document_type.'-'.$customer->document_number;
        }
        $this->showCustomerModal = false;
    }

    public function saveCustomer(): void
    {
        if (trim($this->customerName) === '' || trim($this->customerDocNumber) === '') {
            Notification::make()
                ->title('Datos incompletos')
                ->body('Cédula/RIF y Nombre del cliente son obligatorios.')
                ->warning()
                ->send();

            return;
        }

        $cleanDoc = preg_replace('/[^0-9]/', '', $this->customerDocNumber);

        $customer = Customer::updateOrCreate(
            [
                'document_type' => strtoupper($this->customerDocType),
                'document_number' => $cleanDoc,
            ],
            [
                'name' => trim($this->customerName),
                'phone' => trim($this->customerPhone),
                'address' => trim($this->customerAddress) ?: 'Ciudad',
            ]
        );

        $this->selectedCustomerId = $customer->id;
        $this->selectedCustomerName = $customer->name;
        $this->selectedCustomerDoc = $customer->document_type.'-'.$customer->document_number;
        $this->showCustomerModal = false;

        Notification::make()
            ->title('Cliente asignado')
            ->body("Cliente {$customer->name} seleccionado para esta venta.")
            ->success()
            ->send();
    }

    public function getCustomersListProperty(): Collection
    {
        $query = Customer::query();
        if ($this->customerSearchQuery !== '') {
            $term = trim($this->customerSearchQuery);
            $query->where(function ($q) use ($term) {
                $q->where('name', 'ilike', "%{$term}%")
                    ->orWhere('document_number', 'ilike', "%{$term}%")
                    ->orWhere('phone', 'ilike', "%{$term}%");
            });
        }

        return $query->latest('id')->take(10)->get();
    }

    // =========================================================================
    // FISCAL CALCULATIONS & WIZARD (Tarea 6)
    // =========================================================================

    public function getFiscalSummaryProperty(): FiscalSummaryDTO
    {
        $items = array_values($this->cart);

        return FiscalCalculationService::calculate(
            items: $items,
            payments: $this->payments,
            bcvRate: $this->bcvRate,
            intendedMethod: $this->selectedPaymentMethod
        );
    }

    public function openPaymentModal(): void
    {
        if (empty($this->cart)) {
            Notification::make()
                ->title('Carrito vacío')
                ->body('Agregue al menos un producto al carrito para cobrar.')
                ->warning()
                ->send();

            return;
        }

        if (! $this->activeShiftId) {
            Notification::make()
                ->title('Turno cerrado')
                ->body('Debe aperturar turno de caja para procesar cobros.')
                ->danger()
                ->send();
            $this->showShiftOpenModal = true;

            return;
        }

        $this->selectedPaymentMethod = 'cash_usd';
        $summary = $this->fiscalSummary;
        $this->paymentAmount = $summary->total_amount_usd;
        $this->paymentReference = '';
        $this->showPaymentModal = true;
    }

    public function setPaymentMethod(string $method): void
    {
        $this->selectedPaymentMethod = $method;

        $currency = $this->getMethodCurrency($method);
        $summary = $this->fiscalSummary;

        $totalPaidUsd = 0.0;
        $totalPaidBs = 0.0;
        foreach ($this->payments as $p) {
            $amt = (float) ($p['amount'] ?? 0);
            if ($p['currency'] === 'USD') {
                $totalPaidUsd += $amt;
                $totalPaidBs += round($amt * $this->bcvRate, 2);
            } else {
                $totalPaidBs += $amt;
                $totalPaidUsd += round($amt / $this->bcvRate, 2);
            }
        }

        $pendingUsd = max(0.0, round($summary->total_amount_usd - $totalPaidUsd, 2));
        $pendingBs = max(0.0, round($summary->total_amount_bs - $totalPaidBs, 2));

        if ($currency === 'USD') {
            $this->paymentAmount = $pendingUsd;
        } else {
            $this->paymentAmount = $pendingBs;
        }
    }

    public function setQuickBill(float $billAmount): void
    {
        $this->selectedPaymentMethod = 'cash_usd';
        $this->paymentAmount = $billAmount;
    }

    public function setExactPayment(string $currency = 'USD'): void
    {
        $summary = $this->fiscalSummary;
        $totalPaidUsd = 0.0;
        $totalPaidBs = 0.0;
        foreach ($this->payments as $p) {
            $amt = (float) ($p['amount'] ?? 0);
            if ($p['currency'] === 'USD') {
                $totalPaidUsd += $amt;
                $totalPaidBs += round($amt * $this->bcvRate, 2);
            } else {
                $totalPaidBs += $amt;
                $totalPaidUsd += round($amt / $this->bcvRate, 2);
            }
        }

        if ($currency === 'USD') {
            $this->selectedPaymentMethod = 'cash_usd';
            $this->paymentAmount = max(0.0, round($summary->total_amount_usd - $totalPaidUsd, 2));
        } else {
            if ($this->selectedPaymentMethod === 'cash_usd') {
                $this->selectedPaymentMethod = 'pos_bs';
            }
            $this->paymentAmount = max(0.0, round($summary->total_amount_bs - $totalPaidBs, 2));
        }
    }

    public function addPayment(): void
    {
        $amount = (float) $this->paymentAmount;
        if ($amount <= 0) {
            Notification::make()
                ->title('Monto requerido')
                ->body('El monto del pago debe ser mayor a 0.')
                ->warning()
                ->send();

            return;
        }

        $currency = $this->getMethodCurrency($this->selectedPaymentMethod);
        $label = $this->getMethodLabel($this->selectedPaymentMethod);
        $appliesIgtf = ($this->selectedPaymentMethod === 'cash_usd');

        $this->payments[] = [
            'method' => $this->selectedPaymentMethod,
            'label' => $label,
            'currency' => $currency,
            'amount' => round($amount, 2),
            'reference' => trim($this->paymentReference),
            'applies_igtf' => $appliesIgtf,
        ];

        $this->paymentReference = '';

        // Update default amount for next payment if balance remains
        $this->recalculatePaymentsToFit();
    }

    public function removePayment(int $index): void
    {
        if (isset($this->payments[$index])) {
            unset($this->payments[$index]);
            $this->payments = array_values($this->payments);
            $this->recalculatePaymentsToFit();
        }
    }

    public function recalculatePaymentsToFit(): void
    {
        $summary = $this->fiscalSummary;
        $totalPaidUsd = 0.0;
        $totalPaidBs = 0.0;

        foreach ($this->payments as $p) {
            $amt = (float) ($p['amount'] ?? 0);
            if ($p['currency'] === 'USD') {
                $totalPaidUsd += $amt;
                $totalPaidBs += round($amt * $this->bcvRate, 2);
            } else {
                $totalPaidBs += $amt;
                $totalPaidUsd += round($amt / $this->bcvRate, 2);
            }
        }

        $pendingUsd = max(0.0, round($summary->total_amount_usd - $totalPaidUsd, 2));
        $pendingBs = max(0.0, round($summary->total_amount_bs - $totalPaidBs, 2));

        $currency = $this->getMethodCurrency($this->selectedPaymentMethod);
        if ($currency === 'USD') {
            $this->paymentAmount = $pendingUsd;
        } else {
            $this->paymentAmount = $pendingBs;
        }
    }

    // =========================================================================
    // SALE PROCESSING & THERMAL TICKET (Tarea 6)
    // =========================================================================

    public function processSale(): void
    {
        if (empty($this->cart)) {
            Notification::make()->title('Carrito vacío')->danger()->send();

            return;
        }

        $shift = $this->getActiveShift();
        if (! $shift) {
            Notification::make()->title('Turno de caja cerrado')->danger()->send();
            $this->showShiftOpenModal = true;

            return;
        }

        if (empty($this->payments)) {
            // Auto add exact payment with selected method if no payments registered yet
            $this->addPayment();
        }

        $summary = $this->fiscalSummary;

        // Verify that payments cover total
        $totalPaidUsd = 0.0;
        $totalPaidBs = 0.0;
        foreach ($this->payments as $p) {
            $amt = (float) ($p['amount'] ?? 0);
            if ($p['currency'] === 'USD') {
                $totalPaidUsd += $amt;
                $totalPaidBs += round($amt * $this->bcvRate, 2);
            } else {
                $totalPaidBs += $amt;
                $totalPaidUsd += round($amt / $this->bcvRate, 2);
            }
        }

        // Allow 0.02 USD / 1 Bs tolerance due to rounding
        if ($totalPaidUsd < ($summary->total_amount_usd - 0.02) && $totalPaidBs < ($summary->total_amount_bs - 1.0)) {
            $remaining = round($summary->total_amount_usd - $totalPaidUsd, 2);
            Notification::make()
                ->title('Pago incompleto')
                ->body("Faltan \${$remaining} USD por cubrir.")
                ->warning()
                ->send();

            return;
        }

        try {
            DB::transaction(function () use ($shift, $summary) {
                // Ensure customer
                $this->ensureDefaultCustomer();
                $customer = $this->getSelectedCustomer() ?? Customer::firstOrCreate(
                    ['document_number' => '00000000'],
                    ['document_type' => 'V', 'name' => 'Consumidor Final', 'address' => 'Ciudad', 'phone' => '0000000000']
                );

                $userId = Auth::id() ?? $shift->user_id ?? 1;

                // Correlative numbers
                $lastId = (int) (Sale::max('id') ?? 0) + 1;
                $invoiceNumber = 'FACT-'.str_pad((string) $lastId, 6, '0', STR_PAD_LEFT);
                $posDocNumber = 'POS-'.str_pad((string) $lastId, 6, '0', STR_PAD_LEFT);
                $fiscalSerial = 'Z7C7028525';

                // 1. Create Sale
                $sale = Sale::create([
                    'user_id' => $userId,
                    'customer_id' => $customer->id,
                    'cash_shift_id' => $shift->id,
                    'invoice_number' => $invoiceNumber,
                    'pos_document_number' => $posDocNumber,
                    'fiscal_serial' => $fiscalSerial,
                    'total_base' => $summary->taxable_base,
                    'total_vat' => $summary->vat_amount,
                    'total_igtf' => $summary->igtf_amount,
                    'total_amount' => $summary->total_amount_usd,
                    'status' => 'completed',
                    'invoice_date' => now()->toDateString(),
                    'accounting_date' => now()->toDateString(),
                    'due_date' => now()->toDateString(),
                ]);

                // 2. Create Sale Items and Inventory Movements
                $ticketItems = [];
                foreach ($this->cart as $itemData) {
                    $product = Product::find($itemData['product_id']);
                    $qty = (float) $itemData['quantity'];
                    $price = (float) $itemData['unit_price'];
                    $cost = $product ? (float) $product->cost : 0.0;
                    $hasVat = (bool) $itemData['has_vat'];
                    $subtotal = round($qty * $price, 2);
                    $vatAmount = ($hasVat && $summary->vat_amount > 0 && $summary->taxable_base > 0)
                        ? round(($subtotal / $summary->taxable_base) * $summary->vat_amount, 2)
                        : 0.0;

                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'product_id' => $itemData['product_id'],
                        'quantity' => $qty,
                        'unit_price' => $price,
                        'unit_cost' => $cost,
                        'vat_amount' => $vatAmount,
                        'subtotal' => $subtotal,
                        'serial_number' => ! empty($itemData['serial_number']) ? $itemData['serial_number'] : null,
                        'warranty_days' => ! empty($itemData['warranty_days']) ? (int) $itemData['warranty_days'] : null,
                    ]);

                    $ticketItems[] = [
                        'name' => $itemData['name'],
                        'tax_type' => $hasVat ? '(G)' : '(E)',
                        'quantity' => $qty,
                        'unit_price' => $price,
                        'subtotal' => $subtotal,
                        'serial_number' => $itemData['serial_number'] ?? '',
                        'warranty_days' => $itemData['warranty_days'] ?? 0,
                    ];
                }

                // 3. Create Payments
                $ticketPayments = [];
                $rateModel = ExchangeRate::where('currency', 'USD')->latest('date_published')->latest('id')->first();

                foreach ($this->payments as $paymentData) {
                    $pm = $this->resolvePaymentMethodModel($paymentData['method']);

                    Payment::create([
                        'sale_id' => $sale->id,
                        'payment_method_id' => $pm->id,
                        'exchange_rate_id' => $rateModel?->id,
                        'amount' => (float) $paymentData['amount'],
                        'reference_number' => $paymentData['reference'] ?: null,
                    ]);

                    $ticketPayments[] = [
                        'name' => $pm->name,
                        'fiscal_name' => match ($paymentData['method']) {
                            'cash_usd' => 'EFE./DIV.',
                            'pos_bs' => 'T/Debito',
                            'cash_bs' => 'Efectivo',
                            'mobile_pay_bs' => 'Pago Movil',
                            'cashea' => 'Cashea',
                            default => $pm->name,
                        },
                        'amount' => (float) $paymentData['amount'],
                        'currency' => $paymentData['currency'],
                        'amount_bs' => $paymentData['currency'] === 'USD' ? round((float) $paymentData['amount'] * $this->bcvRate, 2) : (float) $paymentData['amount'],
                        'amount_usd' => $paymentData['currency'] === 'USD' ? (float) $paymentData['amount'] : round((float) $paymentData['amount'] / $this->bcvRate, 2),
                        'reference' => $paymentData['reference'],
                    ];
                }

                // 4. Build Thermal Ticket Data
                $qrContent = "RIF:J501234567|FACT:{$sale->invoice_number}|FECHA:".now()->format('Y-m-d')."|TOTAL_USD:{$summary->total_amount_usd}|TOTAL_BS:{$summary->total_amount_bs}|IVA:{$summary->vat_amount}|IGTF:{$summary->igtf_amount}";
                $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data='.urlencode($qrContent);

                $this->ticketData = [
                    'company_name' => 'SUPERMERCADOS & TIENDAS VENEZUELA C.A.',
                    'company_rif' => 'J-50123456-7',
                    'company_address' => 'Av. Francisco de Miranda, Centro Empresarial, Piso 1, Caracas',
                    'company_phone' => '(0212) 555-0100',
                    'invoice_number' => $sale->invoice_number,
                    'control_number' => '00-'.str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT),
                    'pos_document_number' => $sale->pos_document_number,
                    'fiscal_serial' => $sale->fiscal_serial,
                    'date_time' => now()->format('d/m/Y H:i:s'),
                    'cashier_name' => $shift->user->name ?? Auth::user()?->name ?? 'Cajero',
                    'register_name' => $shift->cashRegister->name ?? 'Caja 01',
                    'shift_id' => $shift->id,
                    'customer_name' => $customer->name,
                    'customer_doc' => "{$customer->document_type}-{$customer->document_number}",
                    'customer_address' => $customer->address,
                    'customer_phone' => $customer->phone,
                    'items' => $ticketItems,
                    'subtotal' => $summary->subtotal,
                    'subtotal_bs' => $summary->subtotal_bs,
                    'exempt_amount' => $summary->exempt_amount,
                    'exempt_amount_bs' => $summary->exempt_amount_bs,
                    'taxable_base' => $summary->taxable_base,
                    'taxable_base_bs' => $summary->taxable_base_bs,
                    'vat_amount' => $summary->vat_amount,
                    'vat_amount_bs' => $summary->vat_amount_bs,
                    'igtf_base' => $summary->igtf_base,
                    'igtf_base_bs' => $summary->igtf_base_bs,
                    'igtf_amount' => $summary->igtf_amount,
                    'igtf_amount_bs' => $summary->igtf_amount_bs,
                    'total_amount_usd' => $summary->total_amount_usd,
                    'total_amount_bs' => $summary->total_amount_bs,
                    'bcv_rate' => $this->bcvRate,
                    'change_due_usd' => $summary->change_due_usd,
                    'change_due_bs' => $summary->change_due_bs,
                    'payments' => $ticketPayments,
                    'qr_url' => $qrUrl,
                ];
            });

            $this->showPaymentModal = false;
            $this->showTicketModal = true;

            Notification::make()
                ->title('Venta Completada con Éxito')
                ->body('Comprobante fiscal SENIAT generado.')
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Error al procesar la venta')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function newSale(): void
    {
        $this->cart = [];
        $this->payments = [];
        $this->showTicketModal = false;
        $this->showPaymentModal = false;
        $this->searchQuery = '';
        $this->selectedCustomerId = null;
        $this->ensureDefaultCustomer();
    }

    public function setTicketWidth(string $width): void
    {
        $this->ticketWidth = in_array($width, ['58mm', '80mm'], true) ? $width : '80mm';
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function getMethodCurrency(string $method): string
    {
        return $method === 'cash_usd' ? 'USD' : 'VES';
    }

    private function getMethodLabel(string $method): string
    {
        return match ($method) {
            'cash_usd' => 'Efectivo Divisas ($)',
            'cash_bs' => 'Efectivo (Bs)',
            'pos_bs' => 'Tarjeta Débito (Punto)',
            'mobile_pay_bs' => 'Pago Móvil',
            'cashea' => 'Cashea (Cuotas)',
            default => 'Otro',
        };
    }

    private function resolvePaymentMethodModel(string $key): PaymentMethod
    {
        return match ($key) {
            'cash_usd' => PaymentMethod::firstOrCreate(['name' => 'Efectivo Divisas ($)'], ['requires_reference' => false, 'applies_igtf' => true]),
            'cash_bs' => PaymentMethod::firstOrCreate(['name' => 'Efectivo (Bs)'], ['requires_reference' => false, 'applies_igtf' => false]),
            'pos_bs' => PaymentMethod::firstOrCreate(['name' => 'Punto de Venta'], ['requires_reference' => true, 'applies_igtf' => false]),
            'mobile_pay_bs' => PaymentMethod::firstOrCreate(['name' => 'Pago Móvil'], ['requires_reference' => true, 'applies_igtf' => false]),
            'cashea' => PaymentMethod::firstOrCreate(['name' => 'Cashea'], ['requires_reference' => true, 'applies_igtf' => false]),
            default => PaymentMethod::firstOrCreate(['name' => 'Otro'], ['requires_reference' => false, 'applies_igtf' => false]),
        };
    }

    public function getCashRegistersProperty(): Collection
    {
        return CashRegister::where('is_active', true)->get();
    }
}
