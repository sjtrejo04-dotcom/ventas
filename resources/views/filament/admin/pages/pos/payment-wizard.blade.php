<!-- ========================================== -->
<!-- MODAL: WIZARD DE COBRO MULTIMONEDA (Tarea 6) -->
<!-- ========================================== -->
@if($showPaymentModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 bg-black/80 backdrop-blur-md antialiased font-['Hanken_Grotesk']">
        <div class="bg-white dark:bg-[#1A1A1A] border border-gray-200 dark:border-[#2A2A2A] rounded-3xl w-full max-w-4xl overflow-hidden shadow-2xl flex flex-col max-h-[90vh] animate-in fade-in zoom-in-95 duration-200">
            
            <!-- Modal Top Header -->
            <div class="p-5 sm:p-6 bg-gray-50 dark:bg-[#141414] border-b border-gray-200 dark:border-[#2A2A2A] flex items-center justify-between">
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 dark:bg-[#00FF94]/15 text-emerald-600 dark:text-[#00FF94] flex items-center justify-center">
                        <x-heroicon-o-banknotes style="width: 1.6rem; height: 1.6rem;" />
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white font-['Manrope']">Cobro Multimoneda & Fiscal</h3>
                        <p class="text-xs text-gray-500 dark:text-[#9E9E9E] mt-0.5">
                            Cliente: <span class="text-gray-900 dark:text-white font-semibold">{{ $selectedCustomerName }}</span> 
                            • Tasa BCV: <span class="text-emerald-600 dark:text-[#00FF94] font-bold">{{ number_format($bcvRate, 2) }} Bs/USD</span>
                        </p>
                    </div>
                </div>

                <button 
                    type="button" 
                    wire:click="$set('showPaymentModal', false)" 
                    class="w-10 h-10 rounded-full bg-gray-100 hover:bg-gray-200 dark:bg-[#222] text-gray-400 hover:text-gray-900 dark:text-[#9E9E9E] dark:hover:text-white flex items-center justify-center transition-colors"
                >
                    <x-heroicon-o-x-mark class="w-5 h-5" />
                </button>
            </div>

            <!-- Modal Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-12 flex-grow overflow-hidden">
                
                <!-- Left: Method Selection, Numpad & Quick Bills (7 cols) -->
                <div class="lg:col-span-7 p-5 sm:p-6 border-b lg:border-b-0 lg:border-r border-gray-200 dark:border-[#2A2A2A] overflow-y-auto space-y-5">
                    
                    <!-- 1. Método de Pago Selector (Touch Tabs) -->
                    <div>
                        <label class="block text-[11px] uppercase font-bold tracking-wider text-gray-500 dark:text-[#9E9E9E] mb-2.5">
                            1. Seleccione Método de Pago
                        </label>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                            
                            <!-- Divisas USD (Aplica 3% IGTF) -->
                            <button 
                                type="button" 
                                wire:click="setPaymentMethod('cash_usd')"
                                class="p-3 rounded-2xl border text-left flex flex-col justify-between transition-all {{ $selectedPaymentMethod === 'cash_usd' ? 'bg-[#FF5F1F]/10 dark:bg-[#FF5F1F]/15 border-[#FF5F1F] text-gray-900 dark:text-white shadow-md' : 'bg-gray-50 dark:bg-[#141414] border-gray-200 dark:border-[#2A2A2A] text-gray-700 dark:text-gray-300 hover:border-gray-400 dark:hover:border-gray-600' }}"
                            >
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-base font-bold text-emerald-600 dark:text-[#00FF94]">$ USD</span>
                                    <span class="text-[9px] font-extrabold uppercase px-1.5 py-0.5 rounded bg-rose-500/20 text-rose-600 dark:text-rose-300 border border-rose-500/30">+3% IGTF</span>
                                </div>
                                <div>
                                    <p class="font-bold text-xs">Efectivo Divisas</p>
                                    <p class="text-[10px] text-gray-500 dark:text-[#9E9E9E]">Billetes USD (+3% IGTF)</p>
                                </div>
                            </button>

                            <!-- Efectivo Bolívares (0% IGTF) -->
                            <button 
                                type="button" 
                                wire:click="setPaymentMethod('cash_bs')"
                                class="p-3 rounded-2xl border text-left flex flex-col justify-between transition-all {{ $selectedPaymentMethod === 'cash_bs' ? 'bg-[#FF5F1F]/10 dark:bg-[#FF5F1F]/15 border-[#FF5F1F] text-gray-900 dark:text-white shadow-md' : 'bg-gray-50 dark:bg-[#141414] border-gray-200 dark:border-[#2A2A2A] text-gray-700 dark:text-gray-300 hover:border-gray-400 dark:hover:border-gray-600' }}"
                            >
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-base font-bold text-[#FF5F1F]">Bs</span>
                                    <span class="text-[9px] font-extrabold uppercase px-1.5 py-0.5 rounded bg-emerald-500/10 dark:bg-[#00FF94]/15 text-emerald-600 dark:text-[#00FF94] border border-emerald-500/20 dark:border-[#00FF94]/30">0% IGTF</span>
                                </div>
                                <div>
                                    <p class="font-bold text-xs">Efectivo Bs</p>
                                    <p class="text-[10px] text-gray-500 dark:text-[#9E9E9E]">Moneda nacional</p>
                                </div>
                            </button>

                            <!-- Tarjeta Débito / Punto (0% IGTF) -->
                            <button 
                                type="button" 
                                wire:click="setPaymentMethod('pos_bs')"
                                class="p-3 rounded-2xl border text-left flex flex-col justify-between transition-all {{ $selectedPaymentMethod === 'pos_bs' ? 'bg-[#FF5F1F]/10 dark:bg-[#FF5F1F]/15 border-[#FF5F1F] text-gray-900 dark:text-white shadow-md' : 'bg-gray-50 dark:bg-[#141414] border-gray-200 dark:border-[#2A2A2A] text-gray-700 dark:text-gray-300 hover:border-gray-400 dark:hover:border-gray-600' }}"
                            >
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-base font-bold text-blue-600 dark:text-blue-400">💳 POS</span>
                                    <span class="text-[9px] font-extrabold uppercase px-1.5 py-0.5 rounded bg-emerald-500/10 dark:bg-[#00FF94]/15 text-emerald-600 dark:text-[#00FF94] border border-emerald-500/20 dark:border-[#00FF94]/30">0% IGTF</span>
                                </div>
                                <div>
                                    <p class="font-bold text-xs">Tarjeta Débito</p>
                                    <p class="text-[10px] text-gray-500 dark:text-[#9E9E9E]">Punto de Venta</p>
                                </div>
                            </button>

                            <!-- Pago Móvil (0% IGTF) -->
                            <button 
                                type="button" 
                                wire:click="setPaymentMethod('mobile_pay_bs')"
                                class="p-3 rounded-2xl border text-left flex flex-col justify-between transition-all {{ $selectedPaymentMethod === 'mobile_pay_bs' ? 'bg-[#FF5F1F]/10 dark:bg-[#FF5F1F]/15 border-[#FF5F1F] text-gray-900 dark:text-white shadow-md' : 'bg-gray-50 dark:bg-[#141414] border-gray-200 dark:border-[#2A2A2A] text-gray-700 dark:text-gray-300 hover:border-gray-400 dark:hover:border-gray-600' }}"
                            >
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-base font-bold text-purple-600 dark:text-[#A855F7]">📱 Móvil</span>
                                    <span class="text-[9px] font-extrabold uppercase px-1.5 py-0.5 rounded bg-emerald-500/10 dark:bg-[#00FF94]/15 text-emerald-600 dark:text-[#00FF94] border border-emerald-500/20 dark:border-[#00FF94]/30">0% IGTF</span>
                                </div>
                                <div>
                                    <p class="font-bold text-xs">Pago Móvil</p>
                                    <p class="text-[10px] text-gray-500 dark:text-[#9E9E9E]">Transferencia P2P</p>
                                </div>
                            </button>

                            <!-- Cashea (0% IGTF) -->
                            <button 
                                type="button" 
                                wire:click="setPaymentMethod('cashea')"
                                class="p-3 rounded-2xl border text-left flex flex-col justify-between transition-all {{ $selectedPaymentMethod === 'cashea' ? 'bg-[#FF5F1F]/10 dark:bg-[#FF5F1F]/15 border-[#FF5F1F] text-gray-900 dark:text-white shadow-md' : 'bg-gray-50 dark:bg-[#141414] border-gray-200 dark:border-[#2A2A2A] text-gray-700 dark:text-gray-300 hover:border-gray-400 dark:hover:border-gray-600' }}"
                            >
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-base font-bold text-amber-600 dark:text-yellow-400">⚡ Cashea</span>
                                    <span class="text-[9px] font-extrabold uppercase px-1.5 py-0.5 rounded bg-emerald-500/10 dark:bg-[#00FF94]/15 text-emerald-600 dark:text-[#00FF94] border border-emerald-500/20 dark:border-[#00FF94]/30">0% IGTF</span>
                                </div>
                                <div>
                                    <p class="font-bold text-xs">Cashea</p>
                                    <p class="text-[10px] text-gray-500 dark:text-[#9E9E9E]">Cuotas sin interés</p>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- 2. Botones Rápidos de Billetes USD y Monto Exacto -->
                    <div>
                        <label class="block text-[11px] uppercase font-bold tracking-wider text-gray-500 dark:text-[#9E9E9E] mb-2">
                            2. Billetes Rápidos & Montos Exactos
                        </label>
                        <div class="grid grid-cols-4 sm:grid-cols-7 gap-2">
                            @foreach([5, 10, 20, 50, 100] as $bill)
                                <button 
                                    type="button" 
                                    wire:click="setQuickBill({{ $bill }})"
                                    class="py-2.5 bg-emerald-50 dark:bg-[#141414] hover:bg-emerald-100 dark:hover:bg-[#222] border border-emerald-200 dark:border-[#2A2A2A] hover:border-emerald-500 dark:hover:border-[#00FF94] text-emerald-700 dark:text-[#00FF94] rounded-xl font-bold font-['Manrope'] text-sm transition-all text-center"
                                >
                                    ${{ $bill }}
                                </button>
                            @endforeach

                            <button 
                                type="button" 
                                wire:click="setExactPayment('USD')"
                                class="col-span-2 sm:col-span-1 py-2.5 bg-gray-100 hover:bg-gray-200 dark:bg-[#141414] dark:hover:bg-[#222] border border-gray-200 dark:border-[#2A2A2A] hover:border-[#FF5F1F] text-gray-800 dark:text-white rounded-xl font-semibold text-xs transition-all"
                                title="Monto exacto en USD"
                            >
                                Exacto $
                            </button>
                            <button 
                                type="button" 
                                wire:click="setExactPayment('VES')"
                                class="col-span-2 sm:col-span-1 py-2.5 bg-gray-100 hover:bg-gray-200 dark:bg-[#141414] dark:hover:bg-[#222] border border-gray-200 dark:border-[#2A2A2A] hover:border-[#FF5F1F] text-gray-800 dark:text-white rounded-xl font-semibold text-xs transition-all"
                                title="Monto exacto en Bolívares"
                            >
                                Exacto Bs
                            </button>
                        </div>
                    </div>

                    <!-- 3. Campo de Monto y Referencia -->
                    <div class="bg-gray-50 dark:bg-[#141414] p-4 rounded-2xl border border-gray-200 dark:border-[#2A2A2A] space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 dark:text-[#9E9E9E] mb-1">
                                    Monto a Pagar ({{ $selectedPaymentMethod === 'cash_usd' ? 'USD' : 'Bs' }})
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-sm font-bold {{ $selectedPaymentMethod === 'cash_usd' ? 'text-emerald-600 dark:text-[#00FF94]' : 'text-[#FF5F1F]' }}">
                                        {{ $selectedPaymentMethod === 'cash_usd' ? '$' : 'Bs' }}
                                    </span>
                                    <input 
                                        type="number" 
                                        step="0.01" 
                                        min="0.01" 
                                        wire:model="paymentAmount"
                                        class="w-full bg-white dark:bg-[#121212] text-gray-900 dark:text-white pl-10 pr-4 py-3 rounded-xl border border-gray-300 dark:border-[#2A2A2A] text-right font-['Manrope'] font-extrabold text-lg focus:outline-none focus:border-[#FF5F1F]"
                                        placeholder="0.00"
                                    >
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-500 dark:text-[#9E9E9E] mb-1">
                                    N° Referencia / Lote (Opcional)
                                </label>
                                <input 
                                    type="text" 
                                    wire:model="paymentReference"
                                    placeholder="Ej. Últimos 4 dígitos o lote"
                                    class="w-full bg-white dark:bg-[#121212] text-gray-900 dark:text-white px-3.5 py-3 rounded-xl border border-gray-300 dark:border-[#2A2A2A] text-sm focus:outline-none focus:border-[#FF5F1F]"
                                >
                            </div>
                        </div>

                        <button 
                            type="button" 
                            wire:click="addPayment"
                            class="w-full py-3 bg-[#FF5F1F] hover:bg-[#e65319] text-white font-bold text-sm rounded-xl shadow-md flex items-center justify-center gap-2 transform active:scale-98 transition-all"
                        >
                            <x-heroicon-o-plus style="width: 1.15rem; height: 1.15rem;" />
                            <span>Agregar Este Pago al Ticket</span>
                        </button>
                    </div>

                    <!-- 4. Lista de Pagos Agregados -->
                    @if(count($payments) > 0)
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-[11px] uppercase font-bold tracking-wider text-gray-500 dark:text-[#9E9E9E]">Pagos Registrados</span>
                                <span class="text-xs text-emerald-600 dark:text-[#00FF94] font-bold">{{ count($payments) }} {{ count($payments) === 1 ? 'método' : 'métodos' }}</span>
                            </div>
                            <div class="space-y-2 max-h-36 overflow-y-auto pr-1">
                                @foreach($payments as $idx => $pay)
                                    <div class="p-3 bg-gray-50 dark:bg-[#141414] rounded-xl border border-gray-200 dark:border-[#2A2A2A] flex items-center justify-between">
                                        <div class="flex items-center gap-2.5">
                                            <span class="w-7 h-7 rounded-full bg-gray-200 dark:bg-[#222] flex items-center justify-center text-xs font-bold text-gray-700 dark:text-white">
                                                {{ $pay['currency'] === 'USD' ? '$' : 'Bs' }}
                                            </span>
                                            <div>
                                                <p class="text-xs font-bold text-gray-900 dark:text-white">{{ $pay['label'] }}</p>
                                                @if(!empty($pay['reference']))
                                                    <p class="text-[10px] text-gray-500 dark:text-[#9E9E9E]">Ref: {{ $pay['reference'] }}</p>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span class="text-sm font-bold font-['Manrope'] text-gray-900 dark:text-white">
                                                {{ $pay['currency'] === 'USD' ? '$' : 'Bs ' }}{{ number_format($pay['amount'], 2) }}
                                            </span>
                                            <button 
                                                type="button" 
                                                wire:click="removePayment({{ $idx }})"
                                                class="text-rose-500 hover:text-rose-600 dark:text-rose-400 dark:hover:text-rose-300 p-1 rounded-lg hover:bg-rose-500/10 transition-colors"
                                                title="Eliminar pago"
                                            >
                                                <x-heroicon-o-trash style="width: 1rem; height: 1rem;" />
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                </div>

                <!-- Right: Totals, Live Fiscal Calculations, Change Due & Confirm (5 cols) -->
                <div class="lg:col-span-5 p-5 sm:p-6 bg-gray-50/70 dark:bg-[#141414] flex flex-col justify-between overflow-y-auto space-y-5">
                    
                    <div>
                        <h4 class="text-sm font-bold text-gray-900 dark:text-white font-['Manrope'] border-b border-gray-200 dark:border-[#2A2A2A] pb-3 mb-4">
                            Liquidación Fiscal en Tiempo Real
                        </h4>

                        @php $summary = $this->fiscalSummary; @endphp

                        <!-- Resumen Desglosado SENIAT -->
                        <div class="space-y-2 text-xs font-['Hanken_Grotesk'] text-gray-500 dark:text-[#9E9E9E]">
                            <div class="flex justify-between items-center">
                                <span>Subtotal Artículos:</span>
                                <span class="font-semibold text-gray-900 dark:text-white font-['Manrope']">${{ number_format($summary->subtotal, 2) }}</span>
                            </div>

                            @if($summary->exempt_amount > 0)
                                <div class="flex justify-between items-center text-emerald-600 dark:text-emerald-400">
                                    <span>Monto Exento (E):</span>
                                    <span class="font-semibold font-['Manrope']">${{ number_format($summary->exempt_amount, 2) }}</span>
                                </div>
                            @endif

                            @if($summary->vat_amount > 0)
                                <div class="flex justify-between items-center">
                                    <span>Base Gravable (G 16%):</span>
                                    <span class="font-semibold text-gray-900 dark:text-white font-['Manrope']">${{ number_format($summary->taxable_base, 2) }}</span>
                                </div>

                                <div class="flex justify-between items-center">
                                    <span>Impuesto IVA (16%):</span>
                                    <span class="font-semibold text-gray-900 dark:text-white font-['Manrope']">${{ number_format($summary->vat_amount, 2) }}</span>
                                </div>
                            @endif

                            <!-- Regla IGTF Dinámica (Pago Divisas Efectivo vs 100% Bolívares) -->
                            <div class="p-2.5 rounded-xl border {{ $summary->igtf_amount > 0 ? 'bg-rose-500/10 border-rose-500/25 text-rose-600 dark:text-rose-300' : 'bg-gray-100 dark:bg-[#121212] border-gray-200 dark:border-[#2A2A2A] text-gray-600 dark:text-gray-400' }}">
                                <div class="flex justify-between items-center">
                                    <span class="font-semibold">
                                        IGTF Divisas (3%):
                                    </span>
                                    <span class="font-bold font-['Manrope']">
                                        +${{ number_format($summary->igtf_amount, 2) }}
                                    </span>
                                </div>
                                <div class="text-[10px] mt-0.5 opacity-80">
                                    @if($summary->igtf_amount > 0)
                                        Aplica sobre ${{ number_format($summary->igtf_base, 2) }} pagados en efectivo divisas.
                                    @else
                                        Exento de IGTF (Pago 100% en Bolívares o sin divisas efectivo).
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Card Gigante de Totales -->
                        <div class="mt-4 p-4 rounded-2xl bg-white dark:bg-[#121212] border border-gray-200 dark:border-[#2A2A2A] space-y-2">
                            <div class="flex justify-between items-baseline">
                                <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-[#9E9E9E]">Total a Pagar USD</span>
                                <span class="text-2xl font-extrabold text-emerald-600 dark:text-[#00FF94] font-['Manrope']">
                                    ${{ number_format($summary->total_amount_usd, 2) }}
                                </span>
                            </div>
                            <div class="flex justify-between items-baseline pt-1 border-t border-gray-100 dark:border-[#222]">
                                <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-[#9E9E9E]">Equivalente Oficial Bs</span>
                                <span class="text-lg font-bold text-gray-900 dark:text-white font-['Manrope']">
                                    Bs {{ number_format($summary->total_amount_bs, 2) }}
                                </span>
                            </div>
                        </div>

                        <!-- Estado de Saldos y Vuelto (Dual USD & Bs) -->
                        <div class="mt-4 p-4 rounded-2xl bg-gray-100/70 dark:bg-[#181818] border border-gray-200 dark:border-[#2A2A2A] space-y-2 text-xs">
                            @php
                                $totalPaidUsd = 0.0;
                                $totalPaidBs = 0.0;
                                foreach ($payments as $p) {
                                    $amt = (float)($p['amount'] ?? 0);
                                    if ($p['currency'] === 'USD') {
                                        $totalPaidUsd += $amt;
                                        $totalPaidBs += round($amt * $bcvRate, 2);
                                    } else {
                                        $totalPaidBs += $amt;
                                        $totalPaidUsd += round($amt / $bcvRate, 2);
                                    }
                                }
                                $isFullyCovered = ($totalPaidUsd >= ($summary->total_amount_usd - 0.02) || $totalPaidBs >= ($summary->total_amount_bs - 1.0));
                            @endphp

                            <div class="flex justify-between items-center text-gray-500 dark:text-[#9E9E9E]">
                                <span>Total Abonado:</span>
                                <span class="font-bold text-gray-900 dark:text-white font-['Manrope']">
                                    ${{ number_format($totalPaidUsd, 2) }} (Bs {{ number_format($totalPaidBs, 2) }})
                                </span>
                            </div>

                            @if($summary->change_due_usd > 0)
                                <!-- VUELTO / CAMBIO -->
                                <div class="p-3 bg-emerald-500/10 border border-emerald-500/25 rounded-xl">
                                    <div class="flex justify-between items-center text-emerald-700 dark:text-[#00FF94] font-bold">
                                        <span class="flex items-center gap-1.5">
                                            <x-heroicon-m-sparkles class="w-4 h-4" /> Vuelto a Entregar:
                                        </span>
                                        <span class="text-base font-extrabold font-['Manrope']">
                                            ${{ number_format($summary->change_due_usd, 2) }}
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-emerald-700 dark:text-[#00FF94] text-right font-['Manrope'] mt-0.5">
                                        Ó en Bolívares: Bs {{ number_format($summary->change_due_bs, 2) }}
                                    </div>
                                </div>
                            @elseif(!$isFullyCovered)
                                <!-- PENDIENTE POR PAGAR -->
                                @php
                                    $pendingUsd = max(0.0, round($summary->total_amount_usd - $totalPaidUsd, 2));
                                    $pendingBs = max(0.0, round($summary->total_amount_bs - $totalPaidBs, 2));
                                @endphp
                                <div class="p-3 bg-amber-500/10 border border-amber-500/25 rounded-xl text-amber-800 dark:text-amber-300">
                                    <div class="flex justify-between items-center font-bold">
                                        <span>Saldo Pendiente:</span>
                                        <span class="text-base font-extrabold font-['Manrope']">
                                            ${{ number_format($pendingUsd, 2) }}
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-right font-['Manrope'] mt-0.5 opacity-90">
                                        Bs {{ number_format($pendingBs, 2) }}
                                    </div>
                                </div>
                            @else
                                <div class="p-2.5 bg-emerald-500/10 border border-emerald-500/20 rounded-xl text-center text-emerald-700 dark:text-[#00FF94] font-bold text-xs">
                                    ✓ Monto cubierto exactamente. Listo para facturar.
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Botón Gigante de Facturación Fiscal -->
                    <div class="pt-3">
                        <button 
                            type="button" 
                            wire:click="processSale"
                            class="w-full py-4 bg-[#FF5F1F] hover:bg-[#e65319] text-white font-extrabold text-base rounded-2xl shadow-xl shadow-[#FF5F1F]/30 flex items-center justify-center gap-2 transform active:scale-95 transition-all font-['Hanken_Grotesk']"
                        >
                            <x-heroicon-o-check-circle style="width: 1.4rem; height: 1.4rem;" />
                            <span>Confirmar Venta y Generar Ticket</span>
                        </button>
                    </div>

                </div>

            </div>

        </div>
    </div>
@endif
