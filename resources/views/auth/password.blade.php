<x-layouts.app title="Ganti password">
<div class="mx-auto max-w-md">
    <x-judul judul="Ganti password" :sub="auth()->user()->must_change_password ? 'Ganti password awal Anda sebelum memakai aplikasi.' : null"/>
    <form method="POST" action="{{ route('password.update') }}" class="panel space-y-4 p-5">
        @csrf @method('PUT')
        <x-field name="password_lama" label="Password lama" wajib>
            <input id="password_lama" name="password_lama" type="password" required autocomplete="current-password" class="input">
        </x-field>
        <x-field name="password" label="Password baru" hint="Minimal 8 karakter, berisi huruf dan angka." wajib>
            <input id="password" name="password" type="password" required autocomplete="new-password" class="input">
        </x-field>
        <x-field name="password_confirmation" label="Ulangi password baru" wajib>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="input">
        </x-field>
        <button class="btn-primary w-full">Simpan password</button>
    </form>
</div>
</x-layouts.app>
