<!-- ========================================== -->
<!-- MODAL: SELECCIÓN EXPRESS DE CLIENTE        -->
<!-- ========================================== -->
@if($showCustomerModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 dark:bg-black/85 backdrop-blur-md antialiased font-['Hanken_Grotesk']">
        <div class="bg-white dark:bg-[#1A1A1A] border border-gray-200 dark:border-[#2A2A2A] rounded-3xl w-full max-w-xl overflow-hidden shadow-2xl flex flex-col max-h-[85vh] animate-in fade-in zoom-in-95 duration-200 text-gray-900 dark:text-white">
            
            <!-- Header -->
            <div class="p-6 bg-gray-50 dark:bg-[#141414] border-b border-gray-200 dark:border-[#2A2A2A] flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-2xl bg-[#FF5F1F]/15 text-[#FF5F1F] flex items-center justify-center">
                        <x-heroicon-o-user style="width: 1.4rem; height: 1.4rem;" />
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white font-['Manrope']">Cliente de Facturación</h3>
                        <p class="text-xs text-gray-500 dark:text-[#9E9E9E] mt-0.5">Asigne un cliente registrado o cree uno nuevo al instante.</p>
                    </div>
                </div>
                <button 
                    type="button" 
                    wire:click="$set('showCustomerModal', false)" 
                    class="w-9 h-9 rounded-full bg-gray-200 hover:bg-gray-300 dark:bg-[#222] text-gray-500 hover:text-gray-900 dark:text-[#9E9E9E] dark:hover:text-white flex items-center justify-center transition-colors cursor-pointer"
                >
                    <x-heroicon-o-x-mark class="w-5 h-5" />
                </button>
            </div>

            <!-- Body -->
            <div class="p-6 space-y-5 overflow-y-auto">
                
                <!-- Botón de Asignación Rápida: Consumidor Final -->
                <div class="flex items-center justify-between p-3.5 bg-gray-50 dark:bg-[#141414] rounded-2xl border border-gray-200 dark:border-[#2A2A2A]">
                    <div>
                        <p class="font-bold text-sm text-gray-900 dark:text-white">Consumidor Final</p>
                        <p class="text-xs text-gray-500 dark:text-[#9E9E9E]">V-00000000 (Sin personalización fiscal)</p>
                    </div>
                    <button 
                        type="button" 
                        wire:click="setConsumidorFinal" 
                        class="px-4 py-2 bg-[#FF5F1F]/15 hover:bg-[#FF5F1F]/25 text-[#FF5F1F] font-bold text-xs rounded-xl border border-[#FF5F1F]/30 transition-all cursor-pointer"
                    >
                        Seleccionar Consumidor Final
                    </button>
                </div>

                <!-- Formulario Express de Registro / Actualización -->
                <div class="bg-gray-50 dark:bg-[#141414] p-4 rounded-2xl border border-gray-200 dark:border-[#2A2A2A] space-y-3">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-600 dark:text-[#9E9E9E]">
                        Registrar Nuevo Cliente / Actualizar
                    </h4>

                    <div class="grid grid-cols-12 gap-2.5">
                        <div class="col-span-4 sm:col-span-3">
                            <label class="block text-[11px] text-gray-600 dark:text-[#9E9E9E] mb-1 font-medium">Tipo</label>
                            <select 
                                wire:model="customerDocType" 
                                class="w-full bg-white dark:bg-[#121212] text-gray-900 dark:text-white border border-gray-300 dark:border-[#2A2A2A] rounded-xl px-2.5 py-2.5 text-xs font-bold focus:outline-none focus:border-[#FF5F1F]"
                            >
                                <option value="V">V - Venezolano</option>
                                <option value="J">J - Jurídico</option>
                                <option value="E">E - Extranjero</option>
                                <option value="G">G - Gubernamental</option>
                            </select>
                        </div>
                        <div class="col-span-8 sm:col-span-9">
                            <label class="block text-[11px] text-gray-600 dark:text-[#9E9E9E] mb-1 font-medium">Cédula o RIF</label>
                            <input 
                                type="text" 
                                wire:model="customerDocNumber" 
                                placeholder="Ej. 12345678"
                                class="w-full bg-white dark:bg-[#121212] text-gray-900 dark:text-white border border-gray-300 dark:border-[#2A2A2A] rounded-xl px-3.5 py-2.5 text-xs focus:outline-none focus:border-[#FF5F1F]"
                            >
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] text-gray-600 dark:text-[#9E9E9E] mb-1 font-medium">Razón Social o Nombre Completo</label>
                        <input 
                            type="text" 
                            wire:model="customerName" 
                            placeholder="Nombre del cliente o razón social de la empresa"
                            class="w-full bg-white dark:bg-[#121212] text-gray-900 dark:text-white border border-gray-300 dark:border-[#2A2A2A] rounded-xl px-3.5 py-2.5 text-xs focus:outline-none focus:border-[#FF5F1F]"
                        >
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <div>
                            <label class="block text-[11px] text-gray-600 dark:text-[#9E9E9E] mb-1 font-medium">Teléfono / WhatsApp</label>
                            <input 
                                type="text" 
                                wire:model="customerPhone" 
                                placeholder="Ej. 04121234567"
                                class="w-full bg-white dark:bg-[#121212] text-gray-900 dark:text-white border border-gray-300 dark:border-[#2A2A2A] rounded-xl px-3.5 py-2.5 text-xs focus:outline-none focus:border-[#FF5F1F]"
                            >
                        </div>
                        <div>
                            <label class="block text-[11px] text-gray-600 dark:text-[#9E9E9E] mb-1 font-medium">Dirección Fiscal</label>
                            <input 
                                type="text" 
                                wire:model="customerAddress" 
                                placeholder="Ciudad, Municipio..."
                                class="w-full bg-white dark:bg-[#121212] text-gray-900 dark:text-white border border-gray-300 dark:border-[#2A2A2A] rounded-xl px-3.5 py-2.5 text-xs focus:outline-none focus:border-[#FF5F1F]"
                            >
                        </div>
                    </div>

                    <button 
                        type="button" 
                        wire:click="saveCustomer" 
                        class="w-full py-2.5 bg-[#FF5F1F] hover:bg-[#e65319] text-white font-bold text-xs rounded-xl shadow-md transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                    >
                        <x-heroicon-o-check style="width: 1rem; height: 1rem;" />
                        <span>Guardar y Asignar a la Venta</span>
                    </button>
                </div>

                <!-- Lista de Clientes Recientes / Búsqueda -->
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-[#9E9E9E] mb-2">
                        O seleccionar de clientes recientes:
                    </label>
                    <input 
                        type="text" 
                        wire:model.live.debounce.300ms="customerSearchQuery" 
                        placeholder="Buscar por nombre, cédula o teléfono..." 
                        class="w-full bg-white dark:bg-[#141414] text-gray-900 dark:text-white text-xs px-3.5 py-2 rounded-xl border border-gray-300 dark:border-[#2A2A2A] mb-2.5 focus:outline-none focus:border-[#FF5F1F]"
                    >

                    <div class="space-y-1.5 max-h-40 overflow-y-auto pr-1">
                        @forelse($this->customersList as $cust)
                            <div 
                                wire:click="selectCustomer({{ $cust->id }})"
                                class="p-2.5 rounded-xl border border-gray-200 dark:border-[#2A2A2A] bg-gray-50 dark:bg-[#141414] hover:bg-gray-100 dark:hover:bg-[#222] hover:border-[#FF5F1F]/50 flex items-center justify-between cursor-pointer transition-all {{ $selectedCustomerId === $cust->id ? 'border-[#FF5F1F] bg-[#FF5F1F]/10' : '' }}"
                            >
                                <div>
                                    <p class="text-xs font-bold text-gray-900 dark:text-white">{{ $cust->name }}</p>
                                    <p class="text-[11px] text-gray-500 dark:text-[#9E9E9E]">{{ $cust->document_type }}-{{ $cust->document_number }} • {{ $cust->phone ?? 'Sin tlf' }}</p>
                                </div>
                                <span class="text-[11px] text-[#FF5F1F] font-semibold">Seleccionar →</span>
                            </div>
                        @empty
                            <p class="text-xs text-gray-400 dark:text-[#6E6E6E] text-center py-3">No se encontraron clientes.</p>
                        @endforelse
                    </div>
                </div>

            </div>

            <!-- Footer -->
            <div class="p-4 bg-gray-50 dark:bg-[#141414] border-t border-gray-200 dark:border-[#2A2A2A] flex justify-end">
                <button 
                    type="button" 
                    wire:click="$set('showCustomerModal', false)" 
                    class="px-5 py-2.5 bg-gray-200 hover:bg-gray-300 dark:bg-[#222] dark:hover:bg-[#333] text-gray-700 hover:text-gray-900 dark:text-white text-xs font-semibold rounded-xl transition-colors cursor-pointer"
                >
                    Cerrar
                </button>
            </div>

        </div>
    </div>
@endif
