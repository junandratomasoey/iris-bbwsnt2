<x-layouts.app title="Peta kondisi" lebar="h-[calc(100dvh-52px)] lg:h-[100dvh]">
    @include('peta._peta', ['internal' => true, 'api' => route('api.peta'), 'apiDetail' => route('api.di', ['di' => '__ID__'])])
</x-layouts.app>
