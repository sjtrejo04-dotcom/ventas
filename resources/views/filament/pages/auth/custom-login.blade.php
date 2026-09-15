<x-filament-panels::page.simple>
    <div class="flex flex-col items-center justify-center font-['Hanken_Grotesk'] text-gray-800 dark:text-gray-200">
        
        <div class="mb-8 text-center mt-4">
            <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white font-['Manrope'] tracking-tight">NEXUS<span class="text-[#FF5F1F]">POS</span></h1>
            <p class="text-gray-500 dark:text-gray-400 text-xs sm:text-sm mt-1.5 font-['Hanken_Grotesk']">Inicia sesión en tu cuenta para acceder al sistema</p>
        </div>

        <!-- Render the default Filament form -->
        <div class="w-full max-w-md">
            <form wire:submit="authenticate" class="space-y-6">
                {{ $this->form }}

                <button 
                    type="submit" 
                    class="w-full bg-[#FF5F1F] hover:bg-[#e65319] text-white py-3.5 px-6 rounded-full font-bold text-sm shadow-lg shadow-[#FF5F1F]/25 hover:shadow-[#FF5F1F]/40 transform active:scale-95 transition-all font-['Hanken_Grotesk'] flex items-center justify-center gap-2 mt-4"
                >
                    <x-heroicon-o-arrow-right-on-rectangle class="w-5 h-5 text-white" />
                    <span>Iniciar Sesión</span>
                </button>
            </form>
        </div>
    </div>
    
    <!-- Custom CSS for the login container to support light/dark mode -->
    <style>
        body, .fi-simple-main, .fi-simple-layout {
            font-family: 'Hanken Grotesk', sans-serif !important;
        }

        .dark body, .dark .fi-simple-main, .dark .fi-simple-layout {
            background-color: #121212 !important;
        }

        /* Inputs de login con bordes suaves */
        .fi-input-wrp {
            border-radius: 9999px !important;
        }

        .fi-input-wrp input {
            font-family: 'Hanken Grotesk', sans-serif !important;
        }

        .dark .fi-input-wrp {
            background-color: #121212 !important;
            border-color: #2A2A2A !important;
        }

        .dark .fi-input-wrp input {
            color: #FFFFFF !important;
        }

        .fi-input-wrp:focus-within {
            border-color: #FF5F1F !important;
            box-shadow: 0 0 0 1px #FF5F1F !important;
        }
    </style>
</x-filament-panels::page.simple>
