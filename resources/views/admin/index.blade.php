<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Panel de administración') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-gray-900">
                <a class="text-indigo-600 underline" href="{{ route('admin.orders.index') }}">{{ __('Pedidos') }}</a>
            </div>
        </div>
    </div>
</x-app-layout>
