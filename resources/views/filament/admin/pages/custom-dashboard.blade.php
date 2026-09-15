<x-filament-panels::page>
    <div class="flex flex-col h-full -mt-4 text-gray-800 dark:text-gray-200 antialiased font-['Hanken_Grotesk']">
        
        <!-- ========================================== -->
        <!-- 1. ACCESO RÁPIDO AL PUNTO DE VENTA (POS)   -->
        <!-- ========================================== -->
        <div class="bg-gradient-to-r from-[#1E1E1E] via-[#242424] to-[#1E1E1E] border border-[#333] rounded-3xl p-5 sm:p-6 mb-8 shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4 relative overflow-hidden">
            <div class="flex items-center gap-4 z-10">
                <div class="w-14 h-14 rounded-2xl bg-[#FF5F1F]/20 text-[#FF5F1F] flex items-center justify-center border border-[#FF5F1F]/30 shadow-inner">
                    <x-heroicon-o-computer-desktop style="width: 2rem; height: 2rem;" />
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-xl font-extrabold text-white font-['Manrope'] tracking-tight">
                            Punto de Venta Táctil (POS)
                        </h2>
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-[#00FF94]/15 text-[#00FF94] border border-[#00FF94]/30">
                            <span class="w-1.5 h-1.5 rounded-full bg-[#00FF94] animate-pulse"></span>
                            SENIAT Multimoneda
                        </span>
                    </div>
                    <p class="text-xs text-gray-400 mt-1 font-['Hanken_Grotesk']">
                        Facturación rápida en mostrador, arqueo ciego de turnos, cálculo de IGTF y ticket térmico.
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3 w-full md:w-auto z-10">
                <a 
                    href="{{ \App\Filament\Admin\Pages\PosTerminal::getUrl() }}" 
                    class="w-full md:w-auto px-6 py-3.5 bg-[#FF5F1F] hover:bg-[#e65319] text-white font-extrabold text-xs rounded-2xl shadow-lg shadow-[#FF5F1F]/30 flex items-center justify-center gap-2 transform active:scale-95 transition-all font-['Hanken_Grotesk']"
                >
                    <x-heroicon-o-shopping-bag style="width: 1.25rem; height: 1.25rem;" />
                    <span>Abrir Terminal POS</span>
                    <span class="text-white/80 font-normal">→</span>
                </a>
            </div>

            <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-[#FF5F1F]/10 rounded-full blur-3xl pointer-events-none"></div>
        </div>

        <!-- ========================================== -->
        <!-- 2. TRES TARJETAS SUPERIORES DE MÉTRICAS    -->
        <!-- ========================================== -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            
            <!-- Card 1: Ventas del Día (#FF5F1F) -->
            <div class="bg-white dark:bg-gray-900 rounded-2xl p-6 border border-gray-200 dark:border-gray-700 relative overflow-hidden group hover:border-gray-300 dark:hover:border-gray-600 transition-all">
                <div class="flex justify-between items-center mb-4">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Ventas del Día</span>
                    <div class="w-10 h-10 rounded-full bg-[#FF5F1F]/15 flex items-center justify-center text-[#FF5F1F]">
                        <x-heroicon-o-arrow-trending-up style="width: 1.25rem; height: 1.25rem;" />
                    </div>
                </div>
                <div class="space-y-1">
                    <h2 class="text-gray-900 dark:text-white text-3xl font-extrabold font-['Manrope'] tracking-tight">
                        ${{ number_format($todaySales, 2) }}
                    </h2>
                    <div class="flex items-center gap-2 text-xs pt-1.5 font-['Hanken_Grotesk']">
                        <!-- Real percentage comparison could go here later -->
                    </div>
                </div>
                <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-[#FF5F1F] to-transparent opacity-60"></div>
            </div>

            <!-- Card 2: Gastos Operativos -->
            <div class="bg-white dark:bg-gray-900 rounded-2xl p-6 border border-gray-200 dark:border-gray-700 relative overflow-hidden group hover:border-gray-300 dark:hover:border-gray-600 transition-all">
                <div class="flex justify-between items-center mb-4">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Gastos Operativos</span>
                    <div class="w-10 h-10 rounded-full bg-rose-500/15 flex items-center justify-center text-rose-500 dark:text-rose-400">
                        <x-heroicon-o-credit-card style="width: 1.25rem; height: 1.25rem;" />
                    </div>
                </div>
                <div class="space-y-1">
                    <h2 class="text-gray-900 dark:text-white text-3xl font-extrabold font-['Manrope'] tracking-tight">
                        ${{ number_format($todayExpenses, 2) }}
                    </h2>
                    <div class="flex items-center gap-2 text-xs pt-1.5 font-['Hanken_Grotesk']">
                        <!-- Real percentage comparison could go here later -->
                    </div>
                </div>
                <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-rose-500 to-transparent opacity-60"></div>
            </div>

            <!-- Card 3: Balance Neto (#00FF94) -->
            <div class="bg-white dark:bg-gray-900 rounded-2xl p-6 border border-gray-200 dark:border-gray-700 relative overflow-hidden group hover:border-gray-300 dark:hover:border-gray-600 transition-all">
                <div class="flex justify-between items-center mb-4">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Balance Neto</span>
                    <div class="w-10 h-10 rounded-full bg-[#00FF94]/15 flex items-center justify-center text-emerald-500 dark:text-[#00FF94]">
                        <x-heroicon-o-banknotes style="width: 1.25rem; height: 1.25rem;" />
                    </div>
                </div>
                <div class="space-y-1">
                    <h2 class="text-gray-900 dark:text-white text-3xl font-extrabold font-['Manrope'] tracking-tight">
                        ${{ number_format($netBalance, 2) }}
                    </h2>
                    <div class="flex items-center gap-2 text-xs pt-1.5 font-['Hanken_Grotesk']">
                        @if($netBalance > 0)
                            <span class="inline-flex items-center gap-1 font-semibold text-emerald-600 dark:text-[#00FF94] bg-emerald-500/10 dark:bg-[#00FF94]/10 px-2.5 py-0.5 rounded-full border border-emerald-500/25 dark:border-[#00FF94]/25">
                                <x-heroicon-m-check-badge style="width: 0.95rem; height: 0.95rem;" /> Balance Positivo
                            </span>
                        @elseif($netBalance < 0)
                            <span class="inline-flex items-center gap-1 font-semibold text-rose-600 dark:text-rose-400 bg-rose-500/10 px-2.5 py-0.5 rounded-full border border-rose-500/25">
                                <x-heroicon-m-exclamation-triangle style="width: 0.95rem; height: 0.95rem;" /> Balance Negativo
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 font-semibold text-gray-600 dark:text-gray-400 bg-gray-500/10 px-2.5 py-0.5 rounded-full border border-gray-500/25">
                                <x-heroicon-m-minus-circle style="width: 0.95rem; height: 0.95rem;" /> Sin Movimientos
                            </span>
                        @endif
                    </div>
                </div>
                <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-500 dark:from-[#00FF94] to-transparent opacity-60"></div>
            </div>

        </div>

        <!-- ========================================== -->
        <!-- 3. TABLA DE ÚLTIMAS TRANSACCIONES          -->
        <!-- ========================================== -->
        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
            
            <!-- Encabezado de la Tabla -->
            <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <h3 class="text-gray-900 dark:text-white text-lg font-bold font-['Manrope']">Últimas Transacciones</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 font-['Hanken_Grotesk']">Registro de cobros, facturas y operaciones del sistema.</p>
                </div>

                <div class="flex items-center gap-2.5">
                    <span class="text-xs text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-700 px-3 py-1.5 rounded-full font-medium">
                        {{ count($recentSales) > 0 ? count($recentSales) : 4 }} transacciones recientes
                    </span>
                </div>
            </div>

            <!-- Contenedor Responsive de la Tabla -->
            <div class="overflow-x-auto">
                <table class="w-full text-left font-['Hanken_Grotesk'] text-sm">
                    
                    <!-- Table Head -->
                    <thead class="bg-gray-50 dark:bg-gray-800/50 text-gray-500 dark:text-gray-400 uppercase text-[11px] font-semibold tracking-wider border-b border-gray-200 dark:border-gray-700">
                        <tr>
                            <th scope="col" class="px-6 py-4">ID Transacción</th>
                            <th scope="col" class="px-6 py-4">Cliente / Concepto</th>
                            <th scope="col" class="px-6 py-4">Método</th>
                            <th scope="col" class="px-6 py-4">Monto</th>
                            <th scope="col" class="px-6 py-4">Estado</th>
                        </tr>
                    </thead>

                    <!-- Table Body -->
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        
                        @if($recentSales->count() > 0)
                            @foreach($recentSales as $sale)
                                @php
                                    $isPaid = ($sale->status === 'paid' || $sale->status === 'completed' || empty($sale->status) || $sale->status === 'completado');
                                    $paymentName = $sale->payments->first()?->paymentMethod?->name ?? 'Efectivo / POS';
                                    $customerName = $sale->customer?->name ?? 'Cliente General';
                                    $initials = strtoupper(substr($customerName, 0, 2));
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900 dark:text-white">
                                        #TRX-{{ str_pad($sale->id, 4, '0', STR_PAD_LEFT) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-[#FF5F1F]/15 text-[#FF5F1F] flex items-center justify-center font-bold text-xs">
                                                {{ $initials }}
                                            </div>
                                            <div>
                                                <div class="font-medium text-gray-900 dark:text-white">{{ $customerName }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $sale->created_at->format('d M, Y • H:i') }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-500 dark:text-gray-400">
                                        {{ $paymentName }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap font-bold text-gray-900 dark:text-white font-['Manrope']">
                                        ${{ number_format($sale->total_amount, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($isPaid)
                                            <!-- Badge Verde Neón (#00FF94) para 'Pagado' -->
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 dark:bg-[#00FF94]/12 text-emerald-600 dark:text-[#00FF94] border border-emerald-500/30 dark:border-[#00FF94]/30">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 dark:bg-[#00FF94]"></span>
                                                Pagado
                                            </span>
                                        @else
                                            <!-- Badge Morado (#A855F7) para estados secundarios -->
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-[#A855F7]/15 text-[#A855F7] border border-[#A855F7]/30">
                                                <span class="w-1.5 h-1.5 rounded-full bg-[#A855F7]"></span>
                                                En Proceso
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @endif

                    </tbody>
                </table>
            </div>

            <!-- Table Footer -->
            <div class="p-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 font-['Hanken_Grotesk']">
                <span>Historial de transacciones de hoy</span>
                <div class="flex items-center gap-2">
                    <button class="px-3.5 py-1.5 rounded-full bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-700 hover:text-gray-900 dark:hover:text-white transition-colors disabled:opacity-40" disabled>Anterior</button>
                    <button class="px-3.5 py-1.5 rounded-full bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-700 hover:text-gray-900 dark:hover:text-white transition-colors">Siguiente</button>
                </div>
            </div>

        </div>

    </div>
</x-filament-panels::page>
