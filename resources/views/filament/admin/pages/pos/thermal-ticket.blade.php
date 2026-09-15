<!-- ========================================== -->
<!-- MODAL POST-VENTA: TICKET TÉRMICO FISCAL   -->
<!-- Compatible con Rollos Térmicos 58mm & 80mm -->
<!-- Plantilla Estándar SENIAT (Providencia Administrativa) -->
<!-- ========================================== -->
@if($showTicketModal && !empty($ticketData))
    <div class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 bg-black/85 backdrop-blur-md antialiased font-['Hanken_Grotesk']">
        <div class="bg-white dark:bg-[#1A1A1A] border border-gray-200 dark:border-[#2A2A2A] rounded-3xl w-full max-w-2xl overflow-hidden shadow-2xl flex flex-col max-h-[92vh] animate-in fade-in zoom-in-95 duration-200">
            
            <!-- Modal Header -->
            <div class="p-5 bg-gray-50 dark:bg-[#141414] border-b border-gray-200 dark:border-[#2A2A2A] flex items-center justify-between no-print">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/15 text-emerald-600 dark:text-[#00FF94] flex items-center justify-center font-bold">
                        ✓
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white font-['Manrope']">Venta Facturada Exitosamente</h3>
                        <p class="text-xs text-gray-500 dark:text-[#9E9E9E]">Factura N° {{ $ticketData['invoice_number'] ?? '' }} • SENIAT</p>
                    </div>
                </div>

                <!-- Selector de Ancho de Rollo Térmico -->
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-500 dark:text-[#9E9E9E]">Ancho papel:</span>
                    <button 
                        type="button" 
                        wire:click="setTicketWidth('80mm')"
                        class="px-2.5 py-1 text-xs rounded-lg font-bold transition-colors {{ $ticketWidth === '80mm' ? 'bg-[#FF5F1F] text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-800 dark:bg-[#222] dark:text-[#9E9E9E] dark:hover:text-white' }}"
                    >
                        80mm (Estándar)
                    </button>
                    <button 
                        type="button" 
                        wire:click="setTicketWidth('58mm')"
                        class="px-2.5 py-1 text-xs rounded-lg font-bold transition-colors {{ $ticketWidth === '58mm' ? 'bg-[#FF5F1F] text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-800 dark:bg-[#222] dark:text-[#9E9E9E] dark:hover:text-white' }}"
                    >
                        58mm (Mini)
                    </button>
                </div>
            </div>

            <!-- Preview Scroll Area -->
            <div class="p-4 sm:p-6 bg-gray-100 dark:bg-[#0E0E0E] flex-grow overflow-y-auto flex justify-center">
                
                <!-- Ticket Térmico Contenedor (Blanco sobre negro en pantalla / Negro sobre blanco al imprimir) -->
                <div 
                    id="thermal-ticket-print-area" 
                    class="bg-white text-black p-4 sm:p-6 shadow-2xl rounded-sm font-mono text-[11px] leading-tight select-all transition-all"
                    style="width: {{ $ticketWidth === '58mm' ? '280px' : '380px' }}; max-width: 100%;"
                >
                    
                    <!-- ENCABEZADO FISCAL -->
                    <div class="text-center space-y-1 pb-2 border-b border-black">
                        <p class="font-extrabold text-sm uppercase tracking-tight">{{ $ticketData['company_name'] }}</p>
                        <p class="font-bold">RIF: {{ $ticketData['company_rif'] }}</p>
                        <p class="text-[10px]">{{ $ticketData['company_address'] }}</p>
                        <p class="text-[10px]">TELF: {{ $ticketData['company_phone'] }}</p>
                    </div>

                    <!-- DATOS DE FACTURA -->
                    <div class="py-2 border-b border-black text-[10px] space-y-0.5">
                        <div class="flex justify-between font-bold text-xs">
                            <span>SENIAT - FACTURA</span>
                            <span>{{ $ticketData['invoice_number'] }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>N° CONTROL:</span>
                            <span class="font-semibold">{{ $ticketData['control_number'] }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>DOC POS:</span>
                            <span>{{ $ticketData['pos_document_number'] }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>FECHA / HORA:</span>
                            <span>{{ $ticketData['date_time'] }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>CAJA / TURNO:</span>
                            <span>{{ $ticketData['register_name'] }} • Turno #{{ $ticketData['shift_id'] }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>CAJERO:</span>
                            <span>{{ $ticketData['cashier_name'] }}</span>
                        </div>
                    </div>

                    <!-- DATOS DEL CLIENTE -->
                    <div class="py-2 border-b border-black text-[10px] space-y-0.5">
                        <p class="font-bold">CLIENTE:</p>
                        <p class="uppercase font-semibold">{{ $ticketData['customer_name'] }}</p>
                        <div class="flex justify-between">
                            <span>CI / RIF:</span>
                            <span class="font-bold">{{ $ticketData['customer_doc'] }}</span>
                        </div>
                        @if(!empty($ticketData['customer_phone']))
                            <div class="flex justify-between">
                                <span>TELÉFONO:</span>
                                <span>{{ $ticketData['customer_phone'] }}</span>
                            </div>
                        @endif
                        @if(!empty($ticketData['customer_address']) && $ticketData['customer_address'] !== 'Ciudad')
                            <p class="text-[9px] truncate">DIR: {{ $ticketData['customer_address'] }}</p>
                        @endif
                    </div>

                    <!-- ÍTEMS / ARTÍCULOS -->
                    <div class="py-2 border-b border-black">
                        <table class="w-full text-left text-[10px]">
                            <thead>
                                <tr class="border-b border-dashed border-black uppercase text-[9px]">
                                    <th class="py-1">Cant × Precio</th>
                                    <th class="py-1">Desc</th>
                                    <th class="py-1 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-dashed divide-gray-300">
                                @foreach($ticketData['items'] as $item)
                                    <tr class="align-top">
                                        <td class="py-1 pr-1" colspan="3">
                                            <div class="font-bold uppercase text-[10px] flex justify-between">
                                                <span>{{ $item['name'] }}</span>
                                                <span>{{ $item['tax_type'] }}</span>
                                            </div>
                                            <div class="flex justify-between text-[9px] text-gray-700">
                                                <span>
                                                    {{ number_format($item['quantity'], 2) }} × ${{ number_format($item['unit_price'], 2) }}
                                                    (Bs {{ number_format($item['unit_price'] * $ticketData['bcv_rate'], 2) }})
                                                </span>
                                                <span class="font-bold text-black">
                                                    Bs {{ number_format($item['subtotal'] * $ticketData['bcv_rate'], 2) }}
                                                    <span class="text-[8px] font-normal text-gray-600">(${{ number_format($item['subtotal'], 2) }})</span>
                                                    {{ $item['tax_type'] === '(G)' ? 'G' : '(E)' }}
                                                </span>
                                            </div>
                                            @if(!empty($item['serial_number']))
                                                <div class="text-[8px] text-gray-800 font-mono">
                                                    SN: {{ $item['serial_number'] }} 
                                                    @if(!empty($item['warranty_days']))
                                                        • Garantía: {{ $item['warranty_days'] }} días
                                                    @endif
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- DESGLOSE FISCAL SENIAT (Modelo PlanSuárez) -->
                    <div class="py-2 border-b border-black text-[10px] space-y-1">
                        @if(($ticketData['exempt_amount_bs'] ?? 0) > 0 || ($ticketData['exempt_amount'] ?? 0) > 0)
                            <div class="flex justify-between">
                                <span>Monto Exento</span>
                                <span class="font-bold">
                                    Bs {{ number_format($ticketData['exempt_amount_bs'] ?? ($ticketData['exempt_amount'] * $ticketData['bcv_rate']), 2) }}
                                    <span class="text-gray-600 font-normal">(${{ number_format($ticketData['exempt_amount'], 2) }})</span>
                                </span>
                            </div>
                        @endif

                        <div class="space-y-0.5">
                            <span class="font-semibold">Base Imponible</span>
                            <div class="flex justify-between pl-2">
                                <span>G 16,00%</span>
                                <span class="font-bold">
                                    Bs {{ number_format($ticketData['taxable_base_bs'] ?? ($ticketData['taxable_base'] * $ticketData['bcv_rate']), 2) }}
                                    <span class="text-gray-600 font-normal">(${{ number_format($ticketData['taxable_base'], 2) }})</span>
                                </span>
                            </div>
                        </div>

                        <div class="space-y-0.5">
                            <span class="font-semibold">Alicuotas IVA</span>
                            <div class="flex justify-between pl-2">
                                <span>G 16,00%</span>
                                <span class="font-bold">
                                    Bs {{ number_format($ticketData['vat_amount_bs'] ?? ($ticketData['vat_amount'] * $ticketData['bcv_rate']), 2) }}
                                    <span class="text-gray-600 font-normal">(${{ number_format($ticketData['vat_amount'], 2) }})</span>
                                </span>
                            </div>
                            @if(($ticketData['igtf_amount'] ?? 0) > 0)
                                <div class="flex justify-between pl-2 font-bold">
                                    <span>IGTF 3,00%</span>
                                    <span>
                                        Bs {{ number_format($ticketData['igtf_amount_bs'] ?? ($ticketData['igtf_amount'] * $ticketData['bcv_rate']), 2) }}
                                        <span class="text-gray-600 font-normal">(${{ number_format($ticketData['igtf_amount'], 2) }})</span>
                                    </span>
                                </div>
                            @endif
                        </div>

                        <div class="pt-1 border-t border-black flex justify-between font-extrabold text-xs">
                            <span>Monto A Pagar</span>
                            <span>
                                Bs {{ number_format($ticketData['total_amount_bs'], 2) }}
                                <span class="text-sm font-black">(${{ number_format($ticketData['total_amount_usd'], 2) }})</span>
                            </span>
                        </div>
                    </div>

                    <!-- FORMAS DE PAGO & VUELTO -->
                    <div class="py-2 border-b border-black text-[10px] space-y-0.5">
                        @foreach($ticketData['payments'] as $pay)
                            <div class="flex justify-between">
                                <span class="font-semibold">{{ $pay['fiscal_name'] ?? $pay['name'] }}</span>
                                <span class="font-bold">
                                    Bs {{ number_format($pay['amount_bs'] ?? ($pay['currency'] === 'USD' ? $pay['amount'] * $ticketData['bcv_rate'] : $pay['amount']), 2) }}
                                    <span class="text-gray-600 font-normal">(${{ number_format($pay['amount_usd'] ?? ($pay['currency'] === 'USD' ? $pay['amount'] : $pay['amount'] / $ticketData['bcv_rate']), 2) }})</span>
                                </span>
                            </div>
                        @endforeach

                        @if(($ticketData['igtf_base'] ?? 0) > 0)
                            <div class="pt-1 mt-1 border-t border-dashed border-gray-400 flex justify-between font-bold">
                                <span>B.I./IGTF:</span>
                                <span>
                                    Bs {{ number_format($ticketData['igtf_base_bs'] ?? ($ticketData['igtf_base'] * $ticketData['bcv_rate']), 2) }}
                                    <span class="text-gray-600 font-normal">(${{ number_format($ticketData['igtf_base'], 2) }})</span>
                                </span>
                            </div>
                        @endif

                        @if(($ticketData['change_due_usd'] ?? 0) > 0)
                            <div class="pt-1 mt-1 border-t border-dashed border-gray-400 font-bold flex justify-between">
                                <span>CAMBIO / VUELTO:</span>
                                <span>
                                    Bs {{ number_format($ticketData['change_due_bs'], 2) }}
                                    <span class="text-gray-600 font-normal">(${{ number_format($ticketData['change_due_usd'], 2) }})</span>
                                </span>
                            </div>
                        @endif

                        <div class="text-[9px] text-gray-700 text-right pt-0.5">
                            (Tasa Oficial BCV: {{ number_format($ticketData['bcv_rate'], 2) }} Bs/USD)
                        </div>
                    </div>

                    <!-- CÓDIGO QR SENIAT & PIE FISCAL -->
                    <div class="pt-2 text-center space-y-1.5">
                        @if(!empty($ticketData['qr_url']))
                            <div class="flex justify-center">
                                <img 
                                    src="{{ $ticketData['qr_url'] }}" 
                                    alt="QR SENIAT" 
                                    class="w-24 h-24 mx-auto border border-black p-0.5"
                                    loading="eager"
                                >
                            </div>
                        @endif

                        <div class="text-[9px] space-y-0.5">
                            <p class="font-bold tracking-wide uppercase text-[8.5px]">CAMBIO MÁXIMO 2 DÍAS CON ESTA FACTURA</p>
                            <p class="font-extrabold text-[11px] pt-1 border-t border-black">TOTAL Bs. {{ number_format($ticketData['total_amount_bs'], 2) }}</p>
                            <p class="font-mono text-[8px] tracking-wider text-gray-700">{{ $ticketData['fiscal_serial'] }}</p>
                            <p class="text-[8px] text-gray-600">GRACIAS POR SU COMPRA</p>
                        </div>
                    </div>

                </div>

            </div>

            <!-- Modal Footer Actions -->
            <div class="p-5 bg-gray-50 dark:bg-[#141414] border-t border-gray-200 dark:border-[#2A2A2A] flex flex-col sm:flex-row items-center justify-between gap-3 no-print">
                <button 
                    type="button" 
                    wire:click="newSale" 
                    class="w-full sm:w-auto px-6 py-3 rounded-2xl bg-gray-200 hover:bg-gray-300 dark:bg-[#222] dark:hover:bg-[#333] text-gray-800 dark:text-white text-xs font-bold flex items-center justify-center gap-2 transition-colors"
                >
                    <x-heroicon-o-plus style="width: 1.15rem; height: 1.15rem;" />
                    <span>Iniciar Nueva Venta [Esc]</span>
                </button>

                <button 
                    type="button" 
                    onclick="window.print()" 
                    class="w-full sm:w-auto px-8 py-3 bg-[#FF5F1F] hover:bg-[#e65319] text-white text-xs font-extrabold rounded-2xl shadow-lg shadow-[#FF5F1F]/25 flex items-center justify-center gap-2 transform active:scale-95 transition-all"
                >
                    <x-heroicon-o-printer style="width: 1.25rem; height: 1.25rem;" />
                    <span>Imprimir Ticket Térmico</span>
                </button>
            </div>

        </div>
    </div>
@endif

<!-- ==================================================== -->
<!-- ESTILOS EXCLUSIVOS PARA IMPRESIÓN DIRECTA @MEDIA PRINT -->
<!-- ==================================================== -->
<style>
    @media print {
        /* Ocultar todo el layout administrativo de Filament */
        body * {
            visibility: hidden !important;
        }
        
        .no-print {
            display: none !important;
        }

        /* Mostrar únicamente el contenedor del ticket */
        #thermal-ticket-print-area,
        #thermal-ticket-print-area * {
            visibility: visible !important;
        }

        #thermal-ticket-print-area {
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            margin: 0 !important;
            padding: 2mm !important;
            width: 100% !important;
            max-width: 80mm !important;
            box-shadow: none !important;
            border-radius: 0 !important;
            background-color: white !important;
            color: black !important;
        }

        @page {
            size: auto;
            margin: 0mm;
        }
    }
</style>
