<!-- ========================================== -->
<!-- MODAL: APERTURA DE TURNO (Guardia Bloqueante) -->
<!-- ========================================== -->
@if($showShiftOpenModal && !$activeShiftId)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 dark:bg-black/85 backdrop-blur-md antialiased font-['Hanken_Grotesk']">
        <div class="bg-white dark:bg-[#1A1A1A] border border-gray-200 dark:border-[#2A2A2A] rounded-3xl w-full max-w-lg overflow-hidden shadow-2xl animate-in fade-in zoom-in-95 duration-200 text-gray-900 dark:text-white">
            <!-- Modal Header -->
            <div class="p-6 bg-gray-50 dark:bg-[#141414] border-b border-gray-200 dark:border-[#2A2A2A] flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-[#FF5F1F]/15 flex items-center justify-center text-[#FF5F1F]">
                        <x-heroicon-o-lock-closed style="width: 1.5rem; height: 1.5rem;" />
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white font-['Manrope']">Apertura de Turno Requerida</h3>
                        <p class="text-xs text-gray-500 dark:text-[#9E9E9E] mt-0.5">Ingrese el fondo inicial para habilitar la terminal POS.</p>
                    </div>
                </div>
            </div>

            <!-- Modal Form Body -->
            <div class="p-6 space-y-5">
                <!-- Selector de Caja Física -->
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-[#9E9E9E] uppercase tracking-wider mb-2">
                        Caja Física / Terminal
                    </label>
                    <div class="grid grid-cols-1 gap-2">
                        <select 
                            wire:model="selectedRegisterId" 
                            class="w-full bg-white dark:bg-[#121212] text-gray-900 dark:text-white border border-gray-300 dark:border-[#2A2A2A] rounded-2xl px-4 py-3 text-sm focus:outline-none focus:border-[#FF5F1F] focus:ring-1 focus:ring-[#FF5F1F] font-['Hanken_Grotesk']"
                        >
                            <option value="">-- Seleccionar caja --</option>
                            @foreach($this->cashRegisters as $register)
                                <option value="{{ $register->id }}">{{ $register->name }} ({{ $register->code }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Fondos Iniciales Duales -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Fondo en Bolívares -->
                    <div class="bg-gray-50 dark:bg-[#141414] p-4 rounded-2xl border border-gray-200 dark:border-[#2A2A2A]">
                        <label class="block text-xs font-medium text-gray-600 dark:text-[#9E9E9E] mb-1">
                            Fondo Inicial en Bolívares (Bs)
                        </label>
                        <div class="relative mt-1">
                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-xs font-bold text-[#FF5F1F]">Bs</span>
                            <input 
                                type="number" 
                                step="0.01" 
                                min="0" 
                                wire:model="openingCashBs" 
                                placeholder="0.00"
                                class="w-full bg-white dark:bg-[#121212] text-gray-900 dark:text-white pl-10 pr-4 py-2.5 rounded-xl border border-gray-300 dark:border-[#2A2A2A] text-right font-['Manrope'] font-bold text-base focus:outline-none focus:border-[#FF5F1F]"
                            >
                        </div>
                    </div>

                    <!-- Fondo en Divisas USD -->
                    <div class="bg-gray-50 dark:bg-[#141414] p-4 rounded-2xl border border-gray-200 dark:border-[#2A2A2A]">
                        <label class="block text-xs font-medium text-gray-600 dark:text-[#9E9E9E] mb-1">
                            Fondo Inicial en Divisas ($ USD)
                        </label>
                        <div class="relative mt-1">
                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-xs font-bold text-emerald-600 dark:text-[#00FF94]">$</span>
                            <input 
                                type="number" 
                                step="0.01" 
                                min="0" 
                                wire:model="openingCashUsd" 
                                placeholder="0.00"
                                class="w-full bg-white dark:bg-[#121212] text-gray-900 dark:text-white pl-8 pr-4 py-2.5 rounded-xl border border-gray-300 dark:border-[#2A2A2A] text-right font-['Manrope'] font-bold text-base focus:outline-none focus:border-[#00FF94]"
                            >
                        </div>
                    </div>
                </div>

                <!-- Tasa BCV Info -->
                <div class="p-3 bg-gray-100 dark:bg-[#121212] rounded-xl border border-gray-200 dark:border-[#2A2A2A] flex items-center justify-between text-xs text-gray-600 dark:text-[#9E9E9E]">
                    <span class="flex items-center gap-1.5">
                        <x-heroicon-m-information-circle class="w-4 h-4 text-[#FF5F1F]" />
                        Tasa Oficial BCV:
                    </span>
                    <span class="font-bold text-emerald-600 dark:text-[#00FF94] font-['Manrope']">
                        {{ number_format($bcvRate, 2) }} Bs/USD
                    </span>
                </div>
            </div>

            <!-- Modal Actions -->
            <div class="p-6 bg-gray-50 dark:bg-[#141414] border-t border-gray-200 dark:border-[#2A2A2A] flex items-center justify-end gap-3">
                <button 
                    type="button" 
                    wire:click="openShift" 
                    class="w-full sm:w-auto px-6 py-3.5 bg-[#FF5F1F] hover:bg-[#e65319] text-white font-bold text-sm rounded-2xl shadow-lg shadow-[#FF5F1F]/25 flex items-center justify-center gap-2 transform active:scale-95 transition-all cursor-pointer"
                >
                    <x-heroicon-o-key style="width: 1.15rem; height: 1.15rem;" />
                    <span>Aperturar Turno y Comenzar</span>
                </button>
            </div>
        </div>
    </div>
@endif

<!-- ==================================================== -->
<!-- MODAL: CIERRE DE TURNO Y ARQUEO CIEGO (Blind Audit) -->
<!-- ==================================================== -->
@if($showShiftCloseModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 dark:bg-black/85 backdrop-blur-md antialiased font-['Hanken_Grotesk']">
        <div class="bg-white dark:bg-[#1A1A1A] border border-gray-200 dark:border-[#2A2A2A] rounded-3xl w-full max-w-xl overflow-hidden shadow-2xl animate-in fade-in zoom-in-95 duration-200 text-gray-900 dark:text-white">
            <!-- Modal Header -->
            <div class="p-6 bg-gray-50 dark:bg-[#141414] border-b border-gray-200 dark:border-[#2A2A2A] flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-rose-500/15 flex items-center justify-center text-rose-500">
                        <x-heroicon-o-lock-open style="width: 1.5rem; height: 1.5rem;" />
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white font-['Manrope']">Cierre de Turno y Arqueo Ciego</h3>
                        <p class="text-xs text-gray-500 dark:text-[#9E9E9E] mt-0.5">Declare el dinero físico y comprobantes. Los montos del sistema están ocultos.</p>
                    </div>
                </div>
                <button 
                    type="button" 
                    wire:click="$set('showShiftCloseModal', false)" 
                    class="w-9 h-9 rounded-full bg-gray-200 hover:bg-gray-300 dark:bg-[#222] text-gray-500 hover:text-gray-900 dark:text-[#9E9E9E] dark:hover:text-white flex items-center justify-center transition-colors cursor-pointer"
                >
                    <x-heroicon-o-x-mark class="w-5 h-5" />
                </button>
            </div>

            <!-- Modal Body: Blind Declaration Inputs -->
            <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                <div class="p-3 bg-[#FF5F1F]/10 border border-[#FF5F1F]/20 rounded-xl text-xs text-[#FF5F1F] flex items-center gap-2">
                    <x-heroicon-o-shield-check class="w-5 h-5 shrink-0" />
                    <span>Auditoría Ciega: Ingrese el conteo físico real. Al confirmar se calcularán las diferencias contables.</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Efectivo Bs Físico -->
                    <div class="bg-gray-50 dark:bg-[#141414] p-4 rounded-2xl border border-gray-200 dark:border-[#2A2A2A]">
                        <label class="block text-xs font-semibold text-gray-900 dark:text-white mb-1">
                            1. Conteo Efectivo Bolívares (Bs)
                        </label>
                        <p class="text-[11px] text-gray-500 dark:text-[#6E6E6E] mb-2">Billetes y monedas físicas contadas en caja</p>
                        <div class="relative">
                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-xs font-bold text-[#FF5F1F]">Bs</span>
                            <input 
                                type="number" 
                                step="0.01" 
                                min="0" 
                                wire:model="declaredCashBs" 
                                class="w-full bg-white dark:bg-[#121212] text-gray-900 dark:text-white pl-10 pr-4 py-2.5 rounded-xl border border-gray-300 dark:border-[#2A2A2A] text-right font-['Manrope'] font-bold text-base focus:outline-none focus:border-[#FF5F1F]"
                                placeholder="0.00"
                            >
                        </div>
                    </div>

                    <!-- Efectivo USD Físico -->
                    <div class="bg-gray-50 dark:bg-[#141414] p-4 rounded-2xl border border-gray-200 dark:border-[#2A2A2A]">
                        <label class="block text-xs font-semibold text-gray-900 dark:text-white mb-1">
                            2. Conteo Efectivo Divisas ($ USD)
                        </label>
                        <p class="text-[11px] text-gray-500 dark:text-[#6E6E6E] mb-2">Billetes en dólares contados en gaveta</p>
                        <div class="relative">
                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-xs font-bold text-emerald-600 dark:text-[#00FF94]">$</span>
                            <input 
                                type="number" 
                                step="0.01" 
                                min="0" 
                                wire:model="declaredCashUsd" 
                                class="w-full bg-white dark:bg-[#121212] text-gray-900 dark:text-white pl-8 pr-4 py-2.5 rounded-xl border border-gray-300 dark:border-[#2A2A2A] text-right font-['Manrope'] font-bold text-base focus:outline-none focus:border-[#00FF94]"
                                placeholder="0.00"
                            >
                        </div>
                    </div>

                    <!-- Lote Punto de Venta / Débito (Vouchers) -->
                    <div class="bg-gray-50 dark:bg-[#141414] p-4 rounded-2xl border border-gray-200 dark:border-[#2A2A2A]">
                        <label class="block text-xs font-semibold text-gray-900 dark:text-white mb-1">
                            3. Cierre Lote Punto de Venta (Bs)
                        </label>
                        <p class="text-[11px] text-gray-500 dark:text-[#6E6E6E] mb-2">Total según reporte de cierre del POS bancario</p>
                        <div class="relative">
                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-xs font-bold text-blue-500 dark:text-blue-400">Bs</span>
                            <input 
                                type="number" 
                                step="0.01" 
                                min="0" 
                                wire:model="declaredPosBs" 
                                class="w-full bg-white dark:bg-[#121212] text-gray-900 dark:text-white pl-10 pr-4 py-2.5 rounded-xl border border-gray-300 dark:border-[#2A2A2A] text-right font-['Manrope'] font-bold text-base focus:outline-none focus:border-blue-400"
                                placeholder="0.00"
                            >
                        </div>
                    </div>

                    <!-- Pago Móvil Confirmado -->
                    <div class="bg-gray-50 dark:bg-[#141414] p-4 rounded-2xl border border-gray-200 dark:border-[#2A2A2A]">
                        <label class="block text-xs font-semibold text-gray-900 dark:text-white mb-1">
                            4. Total Pago Móvil Verificado (Bs)
                        </label>
                        <p class="text-[11px] text-gray-500 dark:text-[#6E6E6E] mb-2">Comprobantes y capturas confirmadas en banco</p>
                        <div class="relative">
                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-xs font-bold text-[#A855F7]">Bs</span>
                            <input 
                                type="number" 
                                step="0.01" 
                                min="0" 
                                wire:model="declaredMobilePayBs" 
                                class="w-full bg-white dark:bg-[#121212] text-gray-900 dark:text-white pl-10 pr-4 py-2.5 rounded-xl border border-gray-300 dark:border-[#2A2A2A] text-right font-['Manrope'] font-bold text-base focus:outline-none focus:border-[#A855F7]"
                                placeholder="0.00"
                            >
                        </div>
                    </div>
                </div>

                <!-- Observaciones del Cajero -->
                <div class="bg-gray-50 dark:bg-[#141414] p-4 rounded-2xl border border-gray-200 dark:border-[#2A2A2A]">
                    <label class="block text-xs font-semibold text-gray-600 dark:text-[#9E9E9E] mb-1">
                        Observaciones o Justificaciones del Turno
                    </label>
                    <textarea 
                        wire:model="shiftNotes" 
                        rows="2" 
                        placeholder="Ej. Billetes de baja denominación cambiados por sencillo, propinas separadas..."
                        class="w-full bg-white dark:bg-[#121212] text-gray-900 dark:text-white p-3 rounded-xl border border-gray-300 dark:border-[#2A2A2A] text-xs focus:outline-none focus:border-[#FF5F1F]"
                    ></textarea>
                </div>
            </div>

            <!-- Modal Footer Actions -->
            <div class="p-6 bg-gray-50 dark:bg-[#141414] border-t border-gray-200 dark:border-[#2A2A2A] flex items-center justify-between gap-3">
                <button 
                    type="button" 
                    wire:click="$set('showShiftCloseModal', false)" 
                    class="px-5 py-3 rounded-2xl bg-gray-200 hover:bg-gray-300 dark:bg-[#222] dark:hover:bg-[#333] text-gray-700 dark:text-gray-300 text-xs font-semibold transition-colors cursor-pointer"
                >
                    Cancelar y Seguir Facturando
                </button>
                <button 
                    type="button" 
                    wire:click="closeShift" 
                    class="px-6 py-3 bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs rounded-2xl shadow-lg shadow-rose-600/25 flex items-center gap-2 transform active:scale-95 transition-all cursor-pointer"
                >
                    <x-heroicon-o-check-badge style="width: 1.15rem; height: 1.15rem;" />
                    <span>Confirmar Arqueo y Cerrar Turno</span>
                </button>
            </div>
        </div>
    </div>
@endif

<!-- ==================================================== -->
<!-- MODAL: RESUMEN DE CIERRE / REPORTE Z AUDITADO       -->
<!-- ==================================================== -->
@if($showShiftReportModal && !empty($closedShiftReport))
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 dark:bg-black/85 backdrop-blur-md antialiased font-['Hanken_Grotesk']">
        <div class="bg-white dark:bg-[#1A1A1A] border border-gray-200 dark:border-[#2A2A2A] rounded-3xl w-full max-w-2xl overflow-hidden shadow-2xl animate-in fade-in zoom-in-95 duration-200 text-gray-900 dark:text-white">
            <!-- Header -->
            <div class="p-6 bg-gray-50 dark:bg-[#141414] border-b border-gray-200 dark:border-[#2A2A2A] flex items-center justify-between">
                <div>
                    <span class="text-[10px] uppercase font-bold tracking-widest text-emerald-600 dark:text-[#00FF94] bg-emerald-500/10 px-2.5 py-1 rounded-full border border-emerald-500/20">
                        Reporte de Turno Finalizado
                    </span>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white font-['Manrope'] mt-2">
                        Arqueo de Turno #{{ $closedShiftReport['id'] ?? '' }}
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-[#9E9E9E] mt-0.5">
                        {{ $closedShiftReport['register'] ?? 'Caja' }} • Cajero: {{ $closedShiftReport['cashier'] ?? '' }}
                    </p>
                </div>
                <div class="text-right text-xs text-gray-500 dark:text-[#9E9E9E]">
                    <div>Apertura: {{ $closedShiftReport['opened_at'] ?? '' }}</div>
                    <div>Cierre: {{ $closedShiftReport['closed_at'] ?? '' }}</div>
                </div>
            </div>

            <!-- Body: Comparisons Table -->
            <div class="p-6 space-y-5 max-h-[70vh] overflow-y-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-[#2A2A2A] text-gray-500 dark:text-[#9E9E9E] uppercase font-semibold text-[10px]">
                            <th class="py-2.5">Concepto</th>
                            <th class="py-2.5 text-right">Sistema</th>
                            <th class="py-2.5 text-right">Declarado</th>
                            <th class="py-2.5 text-right">Diferencia</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-[#2A2A2A] font-['Manrope']">
                        <!-- Efectivo Bs -->
                        <tr>
                            <td class="py-3 text-gray-900 dark:text-white font-['Hanken_Grotesk'] font-medium">
                                Efectivo en Bolívares (Bs)
                            </td>
                            <td class="py-3 text-right text-gray-900 dark:text-white">
                                Bs {{ number_format($closedShiftReport['system_cash_bs'] ?? 0, 2) }}
                            </td>
                            <td class="py-3 text-right text-[#FF5F1F] font-bold">
                                Bs {{ number_format($closedShiftReport['declared_cash_bs'] ?? 0, 2) }}
                            </td>
                            <td class="py-3 text-right font-extrabold">
                                @php $diffBs = $closedShiftReport['difference_cash_bs'] ?? 0; @endphp
                                @if($diffBs > 0)
                                    <span class="text-emerald-600 dark:text-[#00FF94] bg-emerald-500/10 px-2 py-0.5 rounded-full">+Bs {{ number_format($diffBs, 2) }} (Sobrante)</span>
                                @elseif($diffBs < 0)
                                    <span class="text-rose-600 dark:text-rose-400 bg-rose-500/10 px-2 py-0.5 rounded-full">-Bs {{ number_format(abs($diffBs), 2) }} (Faltante)</span>
                                @else
                                    <span class="text-gray-400">Bs 0.00 (Exacto)</span>
                                @endif
                            </td>
                        </tr>

                        <!-- Efectivo Divisas USD -->
                        <tr>
                            <td class="py-3 text-gray-900 dark:text-white font-['Hanken_Grotesk'] font-medium">
                                Efectivo Divisas ($ USD)
                            </td>
                            <td class="py-3 text-right text-gray-900 dark:text-white">
                                ${{ number_format($closedShiftReport['system_cash_usd'] ?? 0, 2) }}
                            </td>
                            <td class="py-3 text-right text-emerald-600 dark:text-[#00FF94] font-bold">
                                ${{ number_format($closedShiftReport['declared_cash_usd'] ?? 0, 2) }}
                            </td>
                            <td class="py-3 text-right font-extrabold">
                                @php $diffUsd = $closedShiftReport['difference_cash_usd'] ?? 0; @endphp
                                @if($diffUsd > 0)
                                    <span class="text-emerald-600 dark:text-[#00FF94] bg-emerald-500/10 px-2 py-0.5 rounded-full">+${{ number_format($diffUsd, 2) }} (Sobrante)</span>
                                @elseif($diffUsd < 0)
                                    <span class="text-rose-600 dark:text-rose-400 bg-rose-500/10 px-2 py-0.5 rounded-full">-${{ number_format(abs($diffUsd), 2) }} (Faltante)</span>
                                @else
                                    <span class="text-gray-400">$0.00 (Exacto)</span>
                                @endif
                            </td>
                        </tr>

                        <!-- Punto de Venta -->
                        <tr>
                            <td class="py-3 text-gray-900 dark:text-white font-['Hanken_Grotesk'] font-medium">
                                Tarjeta Débito / POS
                            </td>
                            <td class="py-3 text-right text-gray-900 dark:text-white">
                                Bs {{ number_format($closedShiftReport['system_pos_bs'] ?? 0, 2) }}
                            </td>
                            <td class="py-3 text-right text-blue-500 dark:text-blue-400 font-bold">
                                Bs {{ number_format($closedShiftReport['declared_pos_bs'] ?? 0, 2) }}
                            </td>
                            <td class="py-3 text-right text-gray-500 dark:text-gray-400 font-medium">
                                Declaración física
                            </td>
                        </tr>

                        <!-- Pago Móvil -->
                        <tr>
                            <td class="py-3 text-gray-900 dark:text-white font-['Hanken_Grotesk'] font-medium">
                                Pago Móvil Verificado
                            </td>
                            <td class="py-3 text-right text-gray-900 dark:text-white">
                                Bs {{ number_format($closedShiftReport['system_mobile_pay_bs'] ?? 0, 2) }}
                            </td>
                            <td class="py-3 text-right text-[#A855F7] font-bold">
                                Bs {{ number_format($closedShiftReport['declared_mobile_pay_bs'] ?? 0, 2) }}
                            </td>
                            <td class="py-3 text-right text-gray-500 dark:text-gray-400 font-medium">
                                Declaración física
                            </td>
                        </tr>

                        <!-- Cashea -->
                        <tr>
                            <td class="py-3 text-gray-900 dark:text-white font-['Hanken_Grotesk'] font-medium">
                                Cashea (Cuotas)
                            </td>
                            <td class="py-3 text-right text-gray-900 dark:text-white">
                                Bs {{ number_format($closedShiftReport['system_cashea_bs'] ?? 0, 2) }}
                            </td>
                            <td class="py-3 text-right text-amber-500 dark:text-yellow-400 font-bold">
                                Sistema
                            </td>
                            <td class="py-3 text-right text-gray-500 dark:text-gray-400 font-medium">
                                Conciliación remota
                            </td>
                        </tr>
                    </tbody>
                </table>

                @if(!empty($closedShiftReport['notes']))
                    <div class="bg-gray-50 dark:bg-[#141414] p-3.5 rounded-2xl border border-gray-200 dark:border-[#2A2A2A] text-xs text-gray-600 dark:text-[#9E9E9E]">
                        <span class="font-bold text-gray-900 dark:text-white block mb-1">Notas del Cajero:</span>
                        {{ $closedShiftReport['notes'] }}
                    </div>
                @endif
            </div>

            <!-- Footer -->
            <div class="p-6 bg-gray-50 dark:bg-[#141414] border-t border-gray-200 dark:border-[#2A2A2A] flex items-center justify-between gap-3">
                <button 
                    type="button" 
                    onclick="window.print()" 
                    class="px-5 py-3 rounded-2xl bg-gray-200 hover:bg-gray-300 dark:bg-[#222] dark:hover:bg-[#333] text-gray-900 dark:text-white text-xs font-bold flex items-center gap-2 transition-colors cursor-pointer"
                >
                    <x-heroicon-o-printer style="width: 1.15rem; height: 1.15rem;" />
                    <span>Imprimir Reporte</span>
                </button>
                <button 
                    type="button" 
                    wire:click="dismissShiftReportModal" 
                    class="px-6 py-3 bg-[#FF5F1F] hover:bg-[#e65319] text-white text-xs font-bold rounded-2xl shadow-lg shadow-[#FF5F1F]/25 flex items-center gap-2 transform active:scale-95 transition-all cursor-pointer"
                >
                    <x-heroicon-o-arrow-path style="width: 1.15rem; height: 1.15rem;" />
                    <span>Aceptar y Preparar Próximo Turno</span>
                </button>
            </div>
        </div>
    </div>
@endif
