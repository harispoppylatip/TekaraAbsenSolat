{{--
    Pesan hasil tindakan dan daftar galat isian.
    Dipanggil di setiap halaman yang bisa menyimpan data.
--}}

@if (session('status'))
    <div class="alert alert-success" role="status">
        <strong>Berhasil</strong>
        <span>{{ session('status') }}</span>
    </div>
@endif

@if (session('warning'))
    <div class="alert alert-warning" role="status">
        <strong>Perhatian</strong>
        <span>{{ session('warning') }}</span>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-error" role="alert">
        <strong>Gagal</strong>
        <span>{{ session('error') }}</span>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-error" role="alert">
        <strong>Periksa isian berikut</strong>
        <ul class="alert-list">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
