<!-- ========================================== -->
<!-- MODAL: N° DE SERIAL Y GARANTÍA POR ÍTEM    -->
<!-- ========================================== -->
@if($showItemSerialModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md antialiased font-['Hanken_Grotesk']">
        <div class="bg-white dark:bg-[#1A1A1A] border border-gray-200 dark:border-[#2A2A2A] rounded-3xl w-full max-w-md overflow-hidden shadow-2xl animate-in fade-in zoom-in-95 duration-200">
            
            <div class="p-5 bg-gray-50 dark:bg-[#141414] border-b border-gray-200 dark:border-[#2A2A2A] flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white font-['Manrope']">Garantía y Serial Fiscal</h3>
                    <p class="text-xs text-gray-500 dark:text-[#9E9E9E]">Datos para el comprobante de entrega técnica.</p>
                </div>
                <button 
                    type="button" 
                    wire:click="$set('showItemSerialModal', false)" 
                    class="w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 dark:bg-[#222] text-gray-400 hover:text-gray-900 dark:text-[#9E9E9E] dark:hover:text-white flex items-center justify-center transition-colors"
                >
                    <x-heroicon-o-x-mark class="w-4 h-4" />
                </button>
            </div>

            <div class="p-5 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-[#9E9E9E] mb-1">
                        Número de Serial / IMEI / MAC
                    </label>
                    <input 
                        type="text" 
                        wire:model="itemSerialNumber"
                        placeholder="Ej. SN-9874561230 o IMEI"
                        class="w-full bg-white dark:bg-[#121212] text-gray-900 dark:text-white px-3.5 py-2.5 rounded-xl border border-gray-300 dark:border-[#2A2A2A] text-xs font-mono focus:outline-none focus:border-[#FF5F1F]"
                    >
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-[#9E9E9E] mb-1">
                        Días de Garantía
                    </label>
                    <div class="grid grid-cols-4 gap-2 mb-2">
                        @foreach([30, 90, 180, 365] as $days)
                            <button 
                                type="button" 
                                wire:click="$set('itemWarrantyDays', {{ $days }})"
                                class="py-1.5 px-2 bg-gray-50 hover:bg-gray-100 dark:bg-[#141414] dark:hover:bg-[#222] border border-gray-200 dark:border-[#2A2A2A] text-xs rounded-lg text-gray-700 dark:text-white font-semibold transition-colors {{ $itemWarrantyDays == $days ? 'border-[#FF5F1F] bg-[#FF5F1F]/10 dark:bg-[#FF5F1F]/20 text-[#FF5F1F]' : '' }}"
                            >
                                {{ $days }} días
                            </button>
                        @endforeach
                    </div>
                    <input 
                        type="number" 
                        min="0"
                        wire:model="itemWarrantyDays"
                        placeholder="Número de días"
                        class="w-full bg-white dark:bg-[#121212] text-gray-900 dark:text-white px-3.5 py-2 rounded-xl border border-gray-300 dark:border-[#2A2A2A] text-xs focus:outline-none focus:border-[#FF5F1F]"
                    >
                </div>
            </div>

            <div class="p-4 bg-gray-50 dark:bg-[#141414] border-t border-gray-200 dark:border-[#2A2A2A] flex justify-end gap-2">
                <button 
                    type="button" 
                    wire:click="$set('showItemSerialModal', false)" 
                    class="px-4 py-2 bg-gray-200 hover:bg-gray-300 dark:bg-[#222] dark:hover:bg-[#333] text-gray-700 dark:text-white text-xs font-semibold rounded-xl transition-colors"
                >
                    Cancelar
                </button>
                <button 
                    type="button" 
                    wire:click="saveItemSerial" 
                    class="px-5 py-2 bg-[#FF5F1F] hover:bg-[#e65319] text-white text-xs font-bold rounded-xl shadow-md"
                >
                    Guardar
                </button>
            </div>

        </div>
    </div>
@endif
