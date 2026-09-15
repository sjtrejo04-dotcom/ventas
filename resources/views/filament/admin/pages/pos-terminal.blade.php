<x-filament-panels::page>
    <x-slot name="header"></x-slot>

    <div 
        x-data="{
            init() {
                // Focus search input initially if shift is open
                this.$nextTick(() => {
                    if (this.$refs.searchInput) {
                        this.$refs.searchInput.focus();
                    }
                });
            },
            handleKeydown(e) {
                // Atajo F2: Enfocar buscador de producto / código de barra
                if (e.key === 'F2') {
                    e.preventDefault();
                    if (this.$refs.searchInput) {
                        this.$refs.searchInput.focus();
                        this.$refs.searchInput.select();
                    }
                }
                // Atajo F3: Modal de Cliente Express
                else if (e.key === 'F3') {
                    e.preventDefault();
                    $wire.openCustomerModal();
                }
                // Atajo F4: Abrir Cobro Multimoneda
                else if (e.key === 'F4') {
                    e.preventDefault();
                    $wire.openPaymentModal();
                }
                // Atajo Esc: Cerrar modales o limpiar búsqueda
                else if (e.key === 'Escape') {
                    if ($wire.showPaymentModal) {
                        $wire.set('showPaymentModal', false);
                    } else if ($wire.showCustomerModal) {
                        $wire.set('showCustomerModal', false);
                    } else if ($wire.showTicketModal) {
                        $wire.newSale();
                    } else if ($wire.searchQuery !== '') {
                        $wire.set('searchQuery', '');
                    }
                }
            }
        }"
        @keydown.window="handleKeydown($event)"
        class="flex flex-col h-[calc(100vh-7.5rem)] -mt-4 text-gray-800 dark:text-gray-200 antialiased font-['Hanken_Grotesk'] select-none"
    >
        
        <!-- ========================================== -->
        <!-- BARRA SUPERIOR DE ESTADO Y ATAJOS          -->
        <!-- ========================================== -->
        <div class="bg-white dark:bg-[#1A1A1A] border border-gray-200 dark:border-[#2A2A2A] rounded-2xl px-5 py-3 mb-4 flex flex-wrap items-center justify-between gap-3 shadow-sm shrink-0">
            
            <!-- Izquierda: Estado de Caja y Turno -->
            <div class="flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-xl bg-[#FF5F1F]/15 text-[#FF5F1F] flex items-center justify-center font-bold">
                    <x-heroicon-o-computer-desktop style="width: 1.35rem; height: 1.35rem;" />
                </div>
                
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-sm font-extrabold text-gray-900 dark:text-white font-['Manrope'] tracking-tight">
                            Terminal POS
                        </h1>
                        @if($activeShiftId)
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-500/12 text-emerald-600 dark:text-[#00FF94] border border-emerald-500/30">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 dark:bg-[#00FF94] animate-pulse"></span>
                                Turno #{{ $activeShiftId }} Abierto
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-500/15 text-rose-600 dark:text-rose-400 border border-rose-500/30">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                Turno Bloqueado
                            </span>
                        @endif
                    </div>
                    
                    <p class="text-[11px] text-gray-500 dark:text-[#9E9E9E] mt-0.5">
                        @if($activeShiftId)
                            {{ $activeShiftRegisterName }} • Cajero: <span class="text-gray-900 dark:text-white font-medium">{{ $activeShiftUserName }}</span>
                        @else
                            Aperture un turno para comenzar a facturar
                        @endif
                    </p>
                </div>
            </div>

            <!-- Centro: Badges de Atajos de Teclado Táctiles -->
            <div class="hidden xl:flex items-center gap-2 text-xs">
                <span class="px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-[#141414] border border-gray-200 dark:border-[#2A2A2A] text-gray-600 dark:text-[#9E9E9E]">
                    <kbd class="text-gray-900 dark:text-white font-bold font-mono">F2</kbd> Buscar
                </span>
                <span class="px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-[#141414] border border-gray-200 dark:border-[#2A2A2A] text-gray-600 dark:text-[#9E9E9E]">
                    <kbd class="text-gray-900 dark:text-white font-bold font-mono">F3</kbd> Cliente
                </span>
                <span class="px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-[#141414] border border-gray-200 dark:border-[#2A2A2A] text-gray-600 dark:text-[#9E9E9E]">
                    <kbd class="text-emerald-600 dark:text-[#00FF94] font-bold font-mono">F4</kbd> Cobrar
                </span>
                <span class="px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-[#141414] border border-gray-200 dark:border-[#2A2A2A] text-gray-600 dark:text-[#9E9E9E]">
                    <kbd class="text-gray-900 dark:text-white font-bold font-mono">Esc</kbd> Cancelar
                </span>
            </div>

            <!-- Derecha: Tasa BCV & Botón Cierre de Turno -->
            <div class="flex items-center gap-3">
                <!-- Tasa BCV Badge -->
                <div class="px-3.5 py-1.5 rounded-xl bg-gray-100 dark:bg-[#141414] border border-gray-200 dark:border-[#2A2A2A] flex items-center gap-2">
                    <span class="text-[11px] text-gray-500 dark:text-[#9E9E9E]">BCV:</span>
                    <span class="text-xs font-bold text-emerald-600 dark:text-[#00FF94] font-['Manrope']">
                        {{ number_format($bcvRate, 2) }} Bs/$
                    </span>
                </div>

                <!-- Botón Cerrar Turno / Arqueo Ciego -->
                @if($activeShiftId)
                    <button 
                        type="button" 
                        wire:click="openCloseShiftModal" 
                        class="px-4 py-2 rounded-xl bg-rose-500/15 hover:bg-rose-500/25 border border-rose-500/30 text-rose-600 dark:text-rose-400 hover:text-rose-700 dark:hover:text-rose-300 text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer"
                    >
                        <x-heroicon-o-lock-closed style="width: 1rem; height: 1rem;" />
                        <span>Cerrar Turno</span>
                    </button>
                @endif
            </div>

        </div>

        <!-- ========================================== -->
        <!-- ESPACIO DE TRABAJO PRINCIPAL (2 COLUMNAS)  -->
        <!-- ========================================== -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 flex-grow overflow-hidden">
            
            <!-- ========================================== -->
            <!-- COLUMNA IZQUIERDA: CATÁLOGO Y BÚSQUEDA (7 COLS) -->
            <!-- ========================================== -->
            <div class="lg:col-span-7 bg-white dark:bg-[#1A1A1A] border border-gray-200 dark:border-[#2A2A2A] rounded-3xl p-5 flex flex-col h-full overflow-hidden shadow-sm">
                
                <!-- Buscador Reactivo con Autofocus y Atajo F2 -->
                <div class="relative mb-3.5 shrink-0">
                    <div class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 dark:text-[#6E6E6E] pointer-events-none">
                        <x-heroicon-o-magnifying-glass style="width: 1.25rem; height: 1.25rem;" />
                    </div>
                    
                    <input 
                        type="text" 
                        x-ref="searchInput"
                        wire:model.live.debounce.250ms="searchQuery"
                        wire:keydown.enter="searchBarcodeOrFirst"
                        placeholder="Buscar producto por nombre o código de barras... [F2]"
                        class="w-full bg-gray-50 dark:bg-[#121212] text-gray-900 dark:text-white text-sm placeholder-gray-400 dark:placeholder-[#6E6E6E] pl-12 pr-28 py-3.5 rounded-2xl border border-gray-200 dark:border-[#2A2A2A] focus:outline-none focus:border-[#FF5F1F] focus:ring-1 focus:ring-[#FF5F1F] transition-all font-['Hanken_Grotesk']"
                    >

                    <div class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center gap-1.5">
                        <span class="text-[10px] text-gray-500 dark:text-[#6E6E6E] font-bold px-1.5 py-0.5 rounded bg-gray-200 dark:bg-[#222] border border-gray-300 dark:border-[#333]">
                            Enter ↵
                        </span>
                        @if($searchQuery !== '')
                            <button 
                                type="button" 
                                wire:click="$set('searchQuery', '')" 
                                class="text-gray-400 hover:text-gray-700 dark:text-[#9E9E9E] dark:hover:text-white p-1 cursor-pointer"
                            >
                                <x-heroicon-o-x-mark class="w-4 h-4" />
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Filtros Táctiles por Categoría (Chips) -->
                <div class="flex items-center gap-2 pb-3 mb-3 border-b border-gray-200 dark:border-[#2A2A2A] overflow-x-auto shrink-0 scrollbar-none">
                    <button 
                        type="button" 
                        wire:click="$set('selectedCategory', 'all')"
                        class="px-4 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition-all cursor-pointer {{ $selectedCategory === 'all' ? 'bg-[#FF5F1F] text-white shadow-md shadow-[#FF5F1F]/20' : 'bg-gray-100 dark:bg-[#141414] text-gray-600 dark:text-[#9E9E9E] hover:text-gray-900 dark:hover:text-white border border-gray-200 dark:border-[#2A2A2A]' }}"
                    >
                        Todos
                    </button>
                    <button 
                        type="button" 
                        wire:click="$set('selectedCategory', 'gravado')"
                        class="px-4 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition-all cursor-pointer {{ $selectedCategory === 'gravado' ? 'bg-[#FF5F1F] text-white shadow-md shadow-[#FF5F1F]/20' : 'bg-gray-100 dark:bg-[#141414] text-gray-600 dark:text-[#9E9E9E] hover:text-gray-900 dark:hover:text-white border border-gray-200 dark:border-[#2A2A2A]' }}"
                    >
                        Gravados (16%)
                    </button>
                    <button 
                        type="button" 
                        wire:click="$set('selectedCategory', 'exento')"
                        class="px-4 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition-all cursor-pointer {{ $selectedCategory === 'exento' ? 'bg-[#FF5F1F] text-white shadow-md shadow-[#FF5F1F]/20' : 'bg-gray-100 dark:bg-[#141414] text-gray-600 dark:text-[#9E9E9E] hover:text-gray-900 dark:hover:text-white border border-gray-200 dark:border-[#2A2A2A]' }}"
                    >
                        Exentos (0%)
                    </button>
                    <button 
                        type="button" 
                        wire:click="$set('selectedCategory', 'en_stock')"
                        class="px-4 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition-all cursor-pointer {{ $selectedCategory === 'en_stock' ? 'bg-[#FF5F1F] text-white shadow-md shadow-[#FF5F1F]/20' : 'bg-gray-100 dark:bg-[#141414] text-gray-600 dark:text-[#9E9E9E] hover:text-gray-900 dark:hover:text-white border border-gray-200 dark:border-[#2A2A2A]' }}"
                    >
                        En Stock
                    </button>
                </div>

                <!-- Grilla Táctil de Tarjetas Grandes de Productos -->
                <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3.5 overflow-y-auto pr-1 flex-grow content-start">
                    @forelse($this->products as $product)
                        @php
                            $priceUsd = (float)$product->price;
                            $priceBs = round($priceUsd * $bcvRate, 2);
                            $inCart = isset($cart[$product->id]);
                        @endphp
                        
                        <div 
                            wire:click="addToCart({{ $product->id }})"
                            class="p-4 rounded-2xl border transition-all cursor-pointer flex flex-col justify-between group transform active:scale-95 select-none {{ $inCart ? 'bg-[#FF5F1F]/10 border-[#FF5F1F]/60 ring-1 ring-[#FF5F1F]/40' : 'bg-gray-50 dark:bg-[#141414] border-gray-200 dark:border-[#2A2A2A] hover:border-[#FF5F1F]/40 hover:bg-gray-100 dark:hover:bg-[#1c1c1c]' }}"
                        >
                            <div>
                                <!-- Icono y Badges -->
                                <div class="flex items-start justify-between mb-2">
                                    <div class="w-10 h-10 rounded-xl bg-[#FF5F1F]/15 text-[#FF5F1F] group-hover:scale-105 flex items-center justify-center text-lg transition-transform">
                                        📦
                                    </div>
                                    <div class="flex flex-col items-end gap-1">
                                        <!-- Stock Badge -->
                                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full {{ $product->stock > 0 ? 'bg-emerald-500/12 text-emerald-600 dark:text-[#00FF94] border border-emerald-500/25' : 'bg-rose-500/12 text-rose-600 dark:text-rose-400 border border-rose-500/25' }}">
                                            {{ $product->stock }} disp.
                                        </span>
                                        <!-- Tax Badge -->
                                        @if($product->has_vat)
                                            <span class="text-[9px] font-extrabold text-[#A855F7] bg-[#A855F7]/10 px-1.5 py-0.5 rounded border border-[#A855F7]/25">
                                                (G) 16%
                                            </span>
                                        @else
                                            <span class="text-[9px] font-extrabold text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 px-1.5 py-0.5 rounded border border-emerald-500/25">
                                                (E) Exento
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <!-- Nombre del Producto -->
                                <h3 class="text-xs font-bold text-gray-900 dark:text-white leading-snug line-clamp-2" title="{{ $product->name }}">
                                    {{ $product->name }}
                                </h3>
                            </div>

                            <!-- Precios Duales ($ y Bs) -->
                            <div class="mt-3 pt-2.5 border-t border-gray-200 dark:border-[#2A2A2A]/80">
                                <div class="text-sm font-extrabold text-emerald-600 dark:text-[#00FF94] font-['Manrope']">
                                    ${{ number_format($priceUsd, 2) }}
                                </div>
                                <div class="text-[11px] font-semibold text-gray-500 dark:text-[#9E9E9E] font-['Manrope']">
                                    Bs {{ number_format($priceBs, 2) }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full text-center py-16 text-gray-400 dark:text-[#6E6E6E]">
                            <x-heroicon-o-cube class="w-12 h-12 mx-auto text-gray-300 dark:text-[#333] mb-2" />
                            <p class="text-sm">No se encontraron productos coincidentes.</p>
                        </div>
                    @endforelse
                </div>

            </div>

            <!-- ========================================== -->
            <!-- COLUMNA DERECHA: TICKET DE VENTA (5 COLS)  -->
            <!-- ========================================== -->
            <div class="lg:col-span-5 bg-white dark:bg-[#1A1A1A] border border-gray-200 dark:border-[#2A2A2A] rounded-3xl p-5 flex flex-col h-full overflow-hidden shadow-sm">
                
                <!-- Encabezado del Ticket & Cliente Express (F3) -->
                <div class="pb-3.5 mb-3 border-b border-gray-200 dark:border-[#2A2A2A] shrink-0 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <h2 class="text-base font-bold text-gray-900 dark:text-white font-['Manrope'] flex items-center gap-2">
                            <x-heroicon-o-shopping-cart style="width: 1.25rem; height: 1.25rem;" class="text-[#FF5F1F]" />
                            <span>Ticket de Venta</span>
                        </h2>
                        <span class="text-xs text-emerald-600 dark:text-[#00FF94] bg-emerald-500/10 border border-emerald-500/20 px-2.5 py-0.5 rounded-full font-bold">
                            {{ count($cart) }} {{ count($cart) === 1 ? 'ítem' : 'ítems' }}
                        </span>
                    </div>

                    <!-- Selector de Cliente Express -->
                    <div class="flex items-center justify-between p-2.5 rounded-2xl bg-gray-50 dark:bg-[#141414] border border-gray-200 dark:border-[#2A2A2A]">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-8 h-8 rounded-full bg-[#FF5F1F]/15 text-[#FF5F1F] flex items-center justify-center font-bold text-xs shrink-0">
                                {{ strtoupper(substr($selectedCustomerName ?? 'C', 0, 1)) }}
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-gray-900 dark:text-white truncate">
                                    {{ $selectedCustomerName }}
                                </p>
                                <p class="text-[10px] text-gray-500 dark:text-[#9E9E9E]">
                                    {{ $selectedCustomerDoc }}
                                </p>
                            </div>
                        </div>

                        <button 
                            type="button" 
                            wire:click="openCustomerModal"
                            class="px-3 py-1.5 rounded-xl bg-gray-200 hover:bg-gray-300 dark:bg-[#222] dark:hover:bg-[#333] text-[#FF5F1F] text-xs font-semibold transition-colors shrink-0 cursor-pointer"
                            title="Cambiar cliente [F3]"
                        >
                            Cliente [F3]
                        </button>
                    </div>
                </div>

                <!-- Lista Desplazable de Ítems en el Carrito -->
                <div class="flex-grow space-y-2.5 overflow-y-auto pr-1">
                    @forelse($cart as $id => $item)
                        @php
                            $itemSubtotalBs = round($item['subtotal'] * $bcvRate, 2);
                        @endphp
                        
                        <div class="p-3 bg-gray-50 dark:bg-[#141414] rounded-2xl border border-gray-200 dark:border-[#2A2A2A] flex flex-col justify-between gap-2 transition-all">
                            
                            <!-- Fila Superior: Nombre y Quitar -->
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <p class="font-bold text-gray-900 dark:text-white text-xs truncate" title="{{ $item['name'] }}">
                                            {{ $item['name'] }}
                                        </p>
                                        <span class="text-[9px] font-bold px-1 py-0.2 rounded {{ $item['has_vat'] ? 'text-[#A855F7] bg-[#A855F7]/10' : 'text-emerald-600 dark:text-emerald-400 bg-emerald-500/10' }}">
                                            {{ $item['has_vat'] ? '(G)' : '(E)' }}
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-gray-500 dark:text-[#9E9E9E] mt-0.5">
                                        ${{ number_format($item['unit_price'], 2) }} c/u 
                                        • Bs {{ number_format($item['unit_price'] * $bcvRate, 2) }}
                                    </p>
                                </div>

                                <button 
                                    type="button" 
                                    wire:click="removeFromCart({{ $id }})"
                                    class="text-rose-500 hover:text-rose-600 dark:text-rose-400 dark:hover:text-rose-300 p-1 rounded-lg hover:bg-rose-500/10 transition-colors cursor-pointer"
                                    title="Eliminar producto"
                                >
                                    <x-heroicon-o-trash style="width: 1rem; height: 1rem;" />
                                </button>
                            </div>

                            <!-- Fila Inferior: Botones Táctiles +, -, Serial y Subtotal -->
                            <div class="flex items-center justify-between pt-1 border-t border-gray-200 dark:border-[#222]">
                                
                                <!-- Controles Táctiles de Cantidad -->
                                <div class="flex items-center gap-1.5">
                                    <!-- Botón Menos -->
                                    <button 
                                        type="button" 
                                        wire:click="decrementQuantity({{ $id }})"
                                        class="w-8 h-8 rounded-xl bg-gray-200 hover:bg-gray-300 dark:bg-[#222] dark:hover:bg-[#333] text-gray-900 dark:text-white flex items-center justify-center font-bold text-sm transform active:scale-90 transition-all cursor-pointer"
                                    >
                                        -
                                    </button>

                                    <!-- Input de Cantidad con soporte decimales -->
                                    <input 
                                        type="number" 
                                        step="any" 
                                        min="0.001"
                                        value="{{ $item['quantity'] }}"
                                        wire:change="updateQuantity({{ $id }}, $event.target.value)"
                                        class="w-14 h-8 bg-white dark:bg-[#121212] text-gray-900 dark:text-white text-center text-xs font-bold rounded-xl border border-gray-300 dark:border-[#2A2A2A] focus:outline-none focus:border-[#FF5F1F]"
                                    >

                                    <!-- Botón Más -->
                                    <button 
                                        type="button" 
                                        wire:click="incrementQuantity({{ $id }})"
                                        class="w-8 h-8 rounded-xl bg-gray-200 hover:bg-gray-300 dark:bg-[#222] dark:hover:bg-[#333] text-gray-900 dark:text-white flex items-center justify-center font-bold text-sm transform active:scale-90 transition-all cursor-pointer"
                                    >
                                        +
                                    </button>

                                    <!-- Botón Serial/Garantía -->
                                    <button 
                                        type="button" 
                                        wire:click="openItemSerialModal({{ $id }})"
                                        class="px-2 py-1 rounded-lg text-[10px] font-semibold transition-colors cursor-pointer {{ !empty($item['serial_number']) ? 'bg-emerald-500/15 text-emerald-600 dark:text-[#00FF94] border border-emerald-500/30' : 'bg-gray-200 dark:bg-[#222] text-gray-600 dark:text-[#9E9E9E] hover:text-gray-900 dark:hover:text-white' }}"
                                        title="Registrar número de serial y garantía"
                                    >
                                        {{ !empty($item['serial_number']) ? 'SN: '.substr($item['serial_number'], 0, 6).'..' : '+ Serial' }}
                                    </button>
                                </div>

                                <!-- Subtotal del Ítem -->
                                <div class="text-right">
                                    <p class="font-extrabold text-gray-900 dark:text-white text-sm font-['Manrope']">
                                        ${{ number_format($item['subtotal'], 2) }}
                                    </p>
                                    <p class="text-[10px] font-semibold text-gray-500 dark:text-[#9E9E9E] font-['Manrope']">
                                        Bs {{ number_format($itemSubtotalBs, 2) }}
                                    </p>
                                </div>

                            </div>

                        </div>
                    @empty
                        <div class="flex flex-col items-center justify-center h-48 text-gray-400 dark:text-[#6E6E6E] text-center">
                            <x-heroicon-o-shopping-cart class="w-12 h-12 text-gray-300 dark:text-[#2A2A2A] mb-2" />
                            <p class="text-xs font-medium">El carrito está vacío.</p>
                            <p class="text-[11px] text-gray-400 dark:text-[#4A4A4A] mt-0.5">Seleccione productos del catálogo o use el buscador [F2].</p>
                        </div>
                    @endforelse
                </div>

                <!-- Footer Fijo: Totalizadores Fiscales y Botón Cobrar -->
                <div class="mt-3 pt-3 border-t border-gray-200 dark:border-[#2A2A2A] shrink-0 space-y-2.5">
                    @php $summary = $this->fiscalSummary; @endphp

                    <!-- Desglose de Impuestos -->
                    <div class="space-y-1 text-xs text-gray-500 dark:text-[#9E9E9E] font-['Hanken_Grotesk']">
                        <div class="flex justify-between items-center">
                            <span>Subtotal:</span>
                            <span class="font-semibold text-gray-900 dark:text-white font-['Manrope']">${{ number_format($summary->subtotal, 2) }}</span>
                        </div>
                        @if($summary->exempt_amount > 0)
                            <div class="flex justify-between items-center text-emerald-600 dark:text-emerald-400">
                                <span>Monto Exento (E):</span>
                                <span class="font-semibold font-['Manrope']">${{ number_format($summary->exempt_amount, 2) }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between items-center">
                            <span>Base 16% (G):</span>
                            <span class="font-semibold text-gray-900 dark:text-white font-['Manrope']">${{ number_format($summary->taxable_base, 2) }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span>IVA 16% (G):</span>
                            <span class="font-semibold text-gray-900 dark:text-white font-['Manrope']">${{ number_format($summary->vat_amount, 2) }}</span>
                        </div>
                        @if($summary->igtf_amount > 0)
                            <div class="flex justify-between items-center text-rose-600 dark:text-rose-300">
                                <span>IGTF (3% Divisas):</span>
                                <span class="font-bold font-['Manrope']">+${{ number_format($summary->igtf_amount, 2) }}</span>
                            </div>
                        @endif
                    </div>

                    <!-- Panel Gigante de Total Dual -->
                    <div class="bg-gray-50 dark:bg-[#141414] p-3.5 rounded-2xl border border-gray-200 dark:border-[#2A2A2A] flex items-center justify-between">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-[#9E9E9E]">Total a Pagar</span>
                            <div class="text-[11px] text-gray-600 dark:text-gray-400 font-['Manrope'] mt-0.5">
                                Bs {{ number_format($summary->total_amount_bs, 2) }}
                            </div>
                        </div>
                        <div class="text-2xl font-extrabold text-emerald-600 dark:text-[#00FF94] font-['Manrope']">
                            ${{ number_format($summary->total_amount_usd, 2) }}
                        </div>
                    </div>

                    <!-- Botones de Acción: Limpiar y Cobrar [F4] -->
                    <div class="grid grid-cols-3 gap-2">
                        <!-- Limpiar Carrito -->
                        <button 
                            type="button" 
                            wire:click="clearCart"
                            class="py-3 rounded-2xl bg-gray-200 hover:bg-gray-300 dark:bg-[#222] dark:hover:bg-[#333] text-gray-700 hover:text-gray-900 dark:text-[#9E9E9E] dark:hover:text-white font-bold text-xs transition-colors flex items-center justify-center gap-1 cursor-pointer"
                            title="Limpiar carrito [Esc]"
                        >
                            <x-heroicon-o-trash style="width: 1rem; height: 1rem;" />
                            <span>Limpiar</span>
                        </button>

                        <!-- Botón Gigante Cobrar [F4] -->
                        <button 
                            type="button" 
                            wire:click="openPaymentModal"
                            class="col-span-2 py-3 bg-[#FF5F1F] hover:bg-[#e65319] text-white font-extrabold text-sm rounded-2xl shadow-lg shadow-[#FF5F1F]/25 flex items-center justify-center gap-2 transform active:scale-95 transition-all font-['Hanken_Grotesk'] cursor-pointer"
                        >
                            <x-heroicon-o-credit-card style="width: 1.25rem; height: 1.25rem;" />
                            <span>Cobrar [F4]</span>
                        </button>
                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- Modales Incluidos -->
    @include('filament.admin.pages.pos.shift-modal')
    @include('filament.admin.pages.pos.payment-wizard')
    @include('filament.admin.pages.pos.customer-modal')
    @include('filament.admin.pages.pos.serial-modal')
    @include('filament.admin.pages.pos.thermal-ticket')

</x-filament-panels::page>
