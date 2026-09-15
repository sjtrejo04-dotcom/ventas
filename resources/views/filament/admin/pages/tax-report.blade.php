<x-filament-panels::page>
    <div class="flex flex-col h-full -mt-4 text-gray-200 antialiased font-['Hanken_Grotesk'] space-y-6">
        
        <!-- ========================================== -->
        <!-- 1. HEADER CON SELECTORES DE FECHA Y PRINT  -->
        <!-- ========================================== -->
        <header class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 bg-white dark:bg-[#1A1A1A] p-6 rounded-2xl border border-gray-200 dark:border-[#2A2A2A]">
            <div>
                <div class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-2xl bg-[#00FF94]/15 text-[#00FF94] flex items-center justify-center shrink-0">
                        <x-heroicon-o-calculator class="w-6 h-6" />
                    </div>
                    <div>
                        <h1 class="text-gray-900 dark:text-white text-2xl sm:text-3xl font-extrabold font-['Manrope'] tracking-tight">Declaración de Impuestos</h1>
                        <p class="text-gray-500 dark:text-[#9E9E9E] text-xs sm:text-sm mt-0.5 font-['Hanken_Grotesk']">Auditoría de Débito Fiscal (IVA), Ventas e Información Contable del período.</p>
                    </div>
                </div>
            </div>
            
            <div class="flex flex-wrap items-center gap-3">
                <!-- Selector de Mes -->
                <div class="flex items-center gap-2 bg-gray-50 dark:bg-[#121212] px-3 py-1.5 rounded-full border border-gray-200 dark:border-[#2A2A2A]">
                    <span class="text-xs text-gray-500 dark:text-[#9E9E9E] font-medium pl-1">Mes:</span>
                    <select 
                        wire:model.live="selectedMonth" 
                        class="bg-transparent text-gray-900 dark:text-white text-xs font-semibold focus:outline-none border-none cursor-pointer py-1 pr-6"
                    >
                        @foreach($months as $num => $name)
                            <option value="{{ $num }}" class="bg-white dark:bg-[#1A1A1A] text-gray-900 dark:text-white">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Selector de Año -->
                <div class="flex items-center gap-2 bg-gray-50 dark:bg-[#121212] px-3 py-1.5 rounded-full border border-gray-200 dark:border-[#2A2A2A]">
                    <span class="text-xs text-gray-500 dark:text-[#9E9E9E] font-medium pl-1">Año:</span>
                    <select 
                        wire:model.live="selectedYear" 
                        class="bg-transparent text-gray-900 dark:text-white text-xs font-semibold focus:outline-none border-none cursor-pointer py-1 pr-6"
                    >
                        @foreach($years as $yearVal)
                            <option value="{{ $yearVal }}" class="bg-white dark:bg-[#1A1A1A] text-gray-900 dark:text-white">{{ $yearVal }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Botón Imprimir / Exportar -->
                <button 
                    type="button"
                    onclick="window.print()"
                    class="inline-flex items-center gap-2 bg-gray-100 dark:bg-[#2A2A2A] hover:bg-gray-200 dark:hover:bg-[#3A3A3A] text-gray-900 dark:text-white px-4 py-2 rounded-full text-xs font-semibold transition-all border border-gray-300 dark:border-[#3A3A3A]"
                    title="Imprimir informe fiscal"
                >
                    <x-heroicon-o-printer class="w-4 h-4 text-gray-500 dark:text-[#9E9E9E]" />
                    <span>Imprimir</span>
                </button>
            </div>
        </header>

        <!-- ========================================== -->
        <!-- 2. CUATRO TARJETAS PRINCIPALES DE TOTALES   -->
        <!-- ========================================== -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            
            <!-- Card 1: Total Ventas (Base) -->
            <div class="bg-white dark:bg-[#1A1A1A] rounded-2xl p-6 border border-gray-200 dark:border-[#2A2A2A] relative overflow-hidden group hover:border-gray-300 dark:border-[#3A3A3A] transition-all">
                <div class="flex justify-between items-center mb-4">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-[#9E9E9E]">Total Ventas (Base)</span>
                    <div class="w-10 h-10 rounded-full bg-[#FF5F1F]/15 flex items-center justify-center text-[#FF5F1F]">
                        <x-heroicon-o-shopping-bag class="w-5 h-5" />
                    </div>
                </div>
                <div class="space-y-1">
                    <h2 class="text-gray-900 dark:text-white text-2xl sm:text-3xl font-extrabold font-['Manrope'] tracking-tight">
                        ${{ number_format($totalSalesBase, 2) }}
                    </h2>
                    <div class="flex items-center gap-2 text-xs pt-1.5 font-['Hanken_Grotesk'] text-gray-500 dark:text-[#9E9E9E]">
                        <span class="inline-flex items-center gap-1 font-semibold text-[#FF5F1F]">
                            {{ $salesCount }} {{ $salesCount === 1 ? 'factura emitida' : 'facturas emitidas' }}
                        </span>
                    </div>
                </div>
                <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-[#FF5F1F] to-transparent opacity-60"></div>
            </div>

            <!-- Card 2: IVA Cobrado (Débito Fiscal) -->
            <div class="bg-white dark:bg-[#1A1A1A] rounded-2xl p-6 border border-gray-200 dark:border-[#2A2A2A] relative overflow-hidden group hover:border-gray-300 dark:border-[#3A3A3A] transition-all">
                <div class="flex justify-between items-center mb-4">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-[#9E9E9E]">IVA Cobrado (Débito)</span>
                    <div class="w-10 h-10 rounded-full bg-[#A855F7]/15 flex items-center justify-center text-[#A855F7]">
                        <x-heroicon-o-receipt-percent class="w-5 h-5" />
                    </div>
                </div>
                <div class="space-y-1">
                    <h2 class="text-gray-900 dark:text-white text-2xl sm:text-3xl font-extrabold font-['Manrope'] tracking-tight">
                        ${{ number_format($totalSalesVat, 2) }}
                    </h2>
                    <div class="flex items-center gap-2 text-xs pt-1.5 font-['Hanken_Grotesk'] text-gray-500 dark:text-[#9E9E9E]">
                        <span class="inline-flex items-center gap-1 font-semibold text-[#A855F7]">
                            16% IVA Débito Fiscal
                        </span>
                    </div>
                </div>
                <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-[#A855F7] to-transparent opacity-60"></div>
            </div>

            <!-- Card 3: Total Gastos (Egresos) -->
            <div class="bg-white dark:bg-[#1A1A1A] rounded-2xl p-6 border border-gray-200 dark:border-[#2A2A2A] relative overflow-hidden group hover:border-gray-300 dark:border-[#3A3A3A] transition-all">
                <div class="flex justify-between items-center mb-4">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-[#9E9E9E]">Total Gastos (Egresos)</span>
                    <div class="w-10 h-10 rounded-full bg-rose-500/15 flex items-center justify-center text-rose-400">
                        <x-heroicon-o-credit-card class="w-5 h-5" />
                    </div>
                </div>
                <div class="space-y-1">
                    <h2 class="text-gray-900 dark:text-white text-2xl sm:text-3xl font-extrabold font-['Manrope'] tracking-tight">
                        ${{ number_format($totalExpenses, 2) }}
                    </h2>
                    <div class="flex items-center gap-2 text-xs pt-1.5 font-['Hanken_Grotesk'] text-gray-500 dark:text-[#9E9E9E]">
                        <span class="inline-flex items-center gap-1 font-medium text-rose-400">
                            {{ $expensesCount }} {{ $expensesCount === 1 ? 'registro de gasto' : 'registros de gasto' }}
                        </span>
                    </div>
                </div>
                <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-rose-500 to-transparent opacity-60"></div>
            </div>

            <!-- Card 4: Total a Declarar (IVA Neto) -->
            <div class="bg-white dark:bg-[#1A1A1A] rounded-2xl p-6 border border-gray-200 dark:border-[#2A2A2A] relative overflow-hidden group hover:border-gray-300 dark:border-[#3A3A3A] transition-all">
                <div class="flex justify-between items-center mb-4">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-[#9E9E9E]">Total a Declarar (IVA)</span>
                    <div class="w-10 h-10 rounded-full bg-[#00FF94]/15 flex items-center justify-center text-[#00FF94]">
                        <x-heroicon-o-check-badge class="w-5 h-5" />
                    </div>
                </div>
                <div class="space-y-1">
                    <h2 class="text-gray-900 dark:text-white text-2xl sm:text-3xl font-extrabold font-['Manrope'] tracking-tight text-[#00FF94]">
                        ${{ number_format($netTaxToDeclare, 2) }}
                    </h2>
                    <div class="flex items-center gap-2 text-xs pt-1.5 font-['Hanken_Grotesk']">
                        <span class="inline-flex items-center gap-1 font-semibold text-[#00FF94] bg-[#00FF94]/10 px-2 py-0.5 rounded-full border border-[#00FF94]/25">
                            IVA Neto Exigible
                        </span>
                    </div>
                </div>
                <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-[#00FF94] to-transparent opacity-60"></div>
            </div>

        </div>

        <!-- ========================================== -->
        <!-- 3. CUADRO DE RESUMEN TRIBUTARIO Y AUDITORÍA-->
        <!-- ========================================== -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Resumen Fiscal Detallado -->
            <div class="lg:col-span-2 bg-white dark:bg-[#1A1A1A] rounded-2xl p-6 border border-gray-200 dark:border-[#2A2A2A] space-y-4">
                <div class="flex items-center justify-between border-b border-gray-200 dark:border-[#2A2A2A] pb-4">
                    <div>
                        <h3 class="text-gray-900 dark:text-white text-lg font-bold font-['Manrope']">Resumen de Liquidación Tributaria</h3>
                        <p class="text-xs text-gray-500 dark:text-[#9E9E9E] mt-0.5">Cálculo consolidado para la declaración mensual ante la administración tributaria.</p>
                    </div>
                    <span class="text-xs font-bold text-[#FF5F1F] bg-[#FF5F1F]/10 border border-[#FF5F1F]/25 px-3 py-1 rounded-full">
                        {{ $periodName }}
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                    <div class="p-4 rounded-xl bg-gray-50 dark:bg-[#121212] border border-gray-200 dark:border-[#2A2A2A] space-y-1">
                        <span class="text-xs text-gray-500 dark:text-[#9E9E9E] font-medium">Facturación Bruta Total (Ventas + IVA)</span>
                        <p class="text-lg font-bold text-gray-900 dark:text-white font-['Manrope']">${{ number_format($totalSalesAmount, 2) }}</p>
                    </div>

                    <div class="p-4 rounded-xl bg-gray-50 dark:bg-[#121212] border border-gray-200 dark:border-[#2A2A2A] space-y-1">
                        <span class="text-xs text-gray-500 dark:text-[#9E9E9E] font-medium">Base Imponible Gravada</span>
                        <p class="text-lg font-bold text-[#FF5F1F] font-['Manrope']">${{ number_format($totalSalesBase, 2) }}</p>
                    </div>

                    <div class="p-4 rounded-xl bg-gray-50 dark:bg-[#121212] border border-gray-200 dark:border-[#2A2A2A] space-y-1">
                        <span class="text-xs text-gray-500 dark:text-[#9E9E9E] font-medium">Débito Fiscal Generado (16%)</span>
                        <p class="text-lg font-bold text-[#A855F7] font-['Manrope']">${{ number_format($totalSalesVat, 2) }}</p>
                    </div>

                    <div class="p-4 rounded-xl bg-gray-50 dark:bg-[#121212] border border-gray-200 dark:border-[#2A2A2A] space-y-1">
                        <span class="text-xs text-gray-500 dark:text-[#9E9E9E] font-medium">Total Gastos y Compras Operativas</span>
                        <p class="text-lg font-bold text-rose-400 font-['Manrope']">${{ number_format($totalExpenses, 2) }}</p>
                    </div>
                </div>

                <div class="p-4 rounded-xl bg-gray-50 dark:bg-[#141414] border border-gray-200 dark:border-[#2A2A2A] flex flex-col sm:flex-row justify-between sm:items-center gap-2">
                    <div>
                        <span class="text-xs text-gray-500 dark:text-[#9E9E9E] font-medium">Resultado Operativo del Período (Base Ventas - Gastos)</span>
                        <p class="text-xs text-[#6E6E6E]">Margen operativo antes de impuestos para el mes de {{ $periodName }}.</p>
                    </div>
                    <span class="text-xl font-extrabold font-['Manrope'] {{ $netBalance >= 0 ? 'text-[#00FF94]' : 'text-rose-400' }}">
                        ${{ number_format($netBalance, 2) }}
                    </span>
                </div>
            </div>

            <!-- Tarjeta de Estado SENIAT / Certificación -->
            <div class="bg-white dark:bg-[#1A1A1A] rounded-2xl p-6 border border-gray-200 dark:border-[#2A2A2A] flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-2xl bg-[#00FF94]/15 text-[#00FF94] flex items-center justify-center shrink-0">
                            <x-heroicon-o-shield-check class="w-6 h-6" />
                        </div>
                        <div>
                            <h3 class="text-gray-900 dark:text-white text-base font-bold font-['Manrope']">Estado de Auditoría</h3>
                            <p class="text-xs text-gray-500 dark:text-[#9E9E9E]">Verificación contable lista</p>
                        </div>
                    </div>

                    <div class="space-y-3 text-xs text-gray-500 dark:text-[#9E9E9E] pt-2">
                        <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-[#2A2A2A]">
                            <span>Período Auditado:</span>
                            <span class="font-bold text-gray-900 dark:text-white">{{ $periodName }}</span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-[#2A2A2A]">
                            <span>Ventas Procesadas:</span>
                            <span class="font-bold text-gray-900 dark:text-white">{{ $salesCount }}</span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-[#2A2A2A]">
                            <span>Gastos Contabilizados:</span>
                            <span class="font-bold text-gray-900 dark:text-white">{{ $expensesCount }}</span>
                        </div>
                        <div class="flex justify-between items-center py-2">
                            <span>Impuesto a Enterar:</span>
                            <span class="font-bold text-[#00FF94] font-['Manrope'] text-sm">${{ number_format($netTaxToDeclare, 2) }}</span>
                        </div>
                    </div>
                </div>

                <div class="pt-6 border-t border-gray-200 dark:border-[#2A2A2A] mt-4">
                    <div class="p-3 bg-gray-50 dark:bg-[#121212] rounded-xl border border-gray-200 dark:border-[#2A2A2A] text-[11px] text-gray-500 dark:text-[#9E9E9E] flex items-center gap-2">
                        <x-heroicon-o-information-circle class="w-5 h-5 text-[#FF5F1F] shrink-0" />
                        <span>Este reporte resume los Débitos Fiscales generados y los Egresos cargados durante el mes seleccionado.</span>
                    </div>
                </div>
            </div>

        </div>

        <!-- ========================================== -->
        <!-- 4. PESTAÑAS DE FACTURAS Y GASTOS AUDITADOS  -->
        <!-- ========================================== -->
        <div x-data="{ auditTab: 'ventas' }" class="bg-white dark:bg-[#1A1A1A] rounded-2xl border border-gray-200 dark:border-[#2A2A2A] overflow-hidden shadow-sm">
            
            <!-- Encabezado con Tabs -->
            <div class="p-6 border-b border-gray-200 dark:border-[#2A2A2A] flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <h3 class="text-gray-900 dark:text-white text-lg font-bold font-['Manrope']">Documentos Fiscales del Período</h3>
                    <p class="text-xs text-gray-500 dark:text-[#9E9E9E] mt-0.5">Auditoría detallada de los registros que componen el cálculo fiscal de {{ $periodName }}.</p>
                </div>

                <!-- Tabs Pill Style -->
                <div class="inline-flex p-1 bg-gray-50 dark:bg-[#121212] border border-gray-200 dark:border-[#2A2A2A] rounded-full self-start">
                    <button 
                        type="button"
                        x-on:click="auditTab = 'ventas'"
                        :class="auditTab === 'ventas' ? 'bg-[#FF5F1F] text-gray-900 dark:text-white shadow-md shadow-[#FF5F1F]/20' : 'text-gray-500 dark:text-[#9E9E9E] hover:text-gray-900 dark:text-white'"
                        class="px-4 py-1.5 rounded-full text-xs font-semibold transition-all flex items-center gap-1.5"
                    >
                        <x-heroicon-o-shopping-bag class="w-3.5 h-3.5" />
                        <span>Ventas ({{ $recentSales->count() }})</span>
                    </button>

                    <button 
                        type="button"
                        x-on:click="auditTab = 'gastos'"
                        :class="auditTab === 'gastos' ? 'bg-rose-500 text-gray-900 dark:text-white shadow-md shadow-rose-500/20' : 'text-gray-500 dark:text-[#9E9E9E] hover:text-gray-900 dark:text-white'"
                        class="px-4 py-1.5 rounded-full text-xs font-semibold transition-all flex items-center gap-1.5"
                    >
                        <x-heroicon-o-credit-card class="w-3.5 h-3.5" />
                        <span>Gastos ({{ $recentExpenses->count() }})</span>
                    </button>
                </div>
            </div>

            <!-- Tab 1: Tabla de Ventas -->
            <div x-show="auditTab === 'ventas'" class="overflow-x-auto">
                <table class="w-full text-left font-['Hanken_Grotesk'] text-sm">
                    <thead class="bg-gray-50 dark:bg-[#141414] text-gray-500 dark:text-[#9E9E9E] uppercase text-[11px] font-semibold tracking-wider border-b border-gray-200 dark:border-[#2A2A2A]">
                        <tr>
                            <th scope="col" class="px-6 py-4">Nro. Factura</th>
                            <th scope="col" class="px-6 py-4">Cliente</th>
                            <th scope="col" class="px-6 py-4">Fecha Factura</th>
                            <th scope="col" class="px-6 py-4 text-right">Base Imponible</th>
                            <th scope="col" class="px-6 py-4 text-right">IVA (16%)</th>
                            <th scope="col" class="px-6 py-4 text-right">Total Factura</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#2A2A2A]">
                        @forelse($recentSales as $sale)
                            <tr class="hover:bg-[#222222]/50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900 dark:text-white">
                                    <button wire:click="mountAction('viewSale', { sale: {{ $sale->id }} })" class="inline-flex items-center gap-1.5 hover:underline text-primary-600 focus:outline-none">
                                        <x-heroicon-o-document-text class="w-4 h-4 text-[#FF5F1F]" />
                                        {{ $sale->invoice_number ?? '#TRX-' . str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT) }}
                                    </button>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-900 dark:text-white font-medium">
                                    {{ $sale->customer?->name ?? 'Cliente General' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-500 dark:text-[#9E9E9E] text-xs">
                                    {{ $sale->invoice_date ?? $sale->created_at->format('Y-m-d') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right font-medium text-gray-900 dark:text-white font-['Manrope']">
                                    ${{ number_format((float) $sale->total_base, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right font-bold text-[#A855F7] font-['Manrope']">
                                    ${{ number_format((float) $sale->total_vat, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right font-extrabold text-gray-900 dark:text-white font-['Manrope']">
                                    ${{ number_format((float) $sale->total_amount, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-[#9E9E9E] text-xs">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <x-heroicon-o-document-magnifying-glass class="w-8 h-8 text-[#6E6E6E]" />
                                        <span>No se encontraron facturas de venta para el período {{ $periodName }}.</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Tab 2: Tabla de Gastos -->
            <div x-show="auditTab === 'gastos'" x-cloak class="overflow-x-auto">
                <table class="w-full text-left font-['Hanken_Grotesk'] text-sm">
                    <thead class="bg-gray-50 dark:bg-[#141414] text-gray-500 dark:text-[#9E9E9E] uppercase text-[11px] font-semibold tracking-wider border-b border-gray-200 dark:border-[#2A2A2A]">
                        <tr>
                            <th scope="col" class="px-6 py-4">Nro. Factura / Control</th>
                            <th scope="col" class="px-6 py-4">Proveedor</th>
                            <th scope="col" class="px-6 py-4">Concepto</th>
                            <th scope="col" class="px-6 py-4">Fecha Gasto</th>
                            <th scope="col" class="px-6 py-4 text-right">Base Imponible</th>
                            <th scope="col" class="px-6 py-4 text-right">IVA</th>
                            <th scope="col" class="px-6 py-4 text-right">Monto Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#2A2A2A]">
                        @forelse($recentExpenses as $expense)
                            <tr class="hover:bg-[#222222]/50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900 dark:text-white">
                                    <span class="inline-flex items-center gap-1.5">
                                        <x-heroicon-o-receipt-percent class="w-4 h-4 text-rose-400" />
                                        {{ $expense->invoice_number ?? '#EXP-' . $expense->id }}
                                        @if($expense->control_number)
                                            <span class="text-[10px] text-[#6E6E6E]">({{ $expense->control_number }})</span>
                                        @endif
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-900 dark:text-white font-medium">
                                    {{ $expense->provider?->name ?? 'Proveedor no asignado' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-500 dark:text-[#9E9E9E] text-xs truncate max-w-xs">
                                    {{ $expense->description ?? 'Sin descripción' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-500 dark:text-[#9E9E9E] text-xs">
                                    {{ $expense->expense_date?->format('Y-m-d') ?? $expense->invoice_date ?? $expense->created_at->format('Y-m-d') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right font-medium text-gray-900 dark:text-white font-['Manrope']">
                                    ${{ number_format((float) ($expense->total_base ?? 0), 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right font-medium text-[#A855F7] font-['Manrope']">
                                    ${{ number_format((float) ($expense->total_vat ?? 0), 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right font-extrabold text-rose-400 font-['Manrope']">
                                    ${{ number_format((float) $expense->total_amount, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-[#9E9E9E] text-xs">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <x-heroicon-o-document-magnifying-glass class="w-8 h-8 text-[#6E6E6E]" />
                                        <span>No se encontraron registros de gastos para el período {{ $periodName }}.</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Footer de la Tabla -->
            <div class="p-4 border-t border-gray-200 dark:border-[#2A2A2A] flex items-center justify-between text-xs text-gray-500 dark:text-[#9E9E9E]">
                <span>Mostrando registros de auditoría para el período fiscal seleccionado.</span>
                <span class="font-medium text-gray-900 dark:text-white">{{ $periodName }}</span>
            </div>

        </div>

    </div>
    
    <x-filament-actions::modals />
</x-filament-panels::page>
