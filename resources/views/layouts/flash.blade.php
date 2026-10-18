@if(session('ok'))
    <div class="mb-4 flex items-start gap-2 rounded-md border border-[#BFD9B0] bg-[#EEF6E9] px-4 py-3 text-sm text-[#2F5A19]" role="status">
        <x-ikon n="cek" class="mt-0.5 h-4 w-4"/> <span>{{ session('ok') }}</span>
    </div>
@endif
@if(session('password_baru'))
    @php $pb = session('password_baru'); @endphp
    <div class="mb-4 rounded-md border border-saluran-100 bg-saluran-50 px-4 py-3 text-sm" role="status">
        <p class="font-semibold">Password baru untuk {{ $pb['username'] }}: <code class="rounded bg-white px-1.5 py-0.5 text-base">{{ $pb['password'] }}</code></p>
        <p class="mt-1 text-basalt-soft">Password ini hanya tampil sekali. Pengguna wajib menggantinya saat login.</p>
        @if($pb['wa'])<a href="{{ $pb['wa'] }}" target="_blank" rel="noopener" class="btn-wa mt-2"><x-ikon n="wa" class="h-3.5 w-3.5"/> Kirim lewat WhatsApp</a>@endif
    </div>
@endif
@if($errors->any() && ! ($sembunyikanError ?? false))
    <div class="mb-4 rounded-md border border-[#E9B7B3] bg-[#FBECEA] px-4 py-3 text-sm text-[#7E1A14]" role="alert">
        <p class="font-semibold">Periksa kembali isian berikut:</p>
        <ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif
