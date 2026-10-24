@extends('layouts.app')

@section('title', 'Ubah Kamera')
@section('note', 'Perbarui pengaturan kamera siaran CCTV atau Web Stream')

@section('content')
    <div>
        <section class="card">
            <div class="section-head">
                <div>
                    <h2 class="section-title">Ubah data kamera: {{ $camera->name }}</h2>
                    <p class="section-note">
                        Perbarui konfigurasi URL siaran, jenis, atau lokasi kamera absen.
                    </p>
                </div>
                <a href="{{ route('cameras.index') }}" class="btn btn-outline btn-small">Kembali</a>
            </div>

            <form method="POST" action="{{ route('cameras.update', $camera) }}" id="form-edit-kamera">
                @csrf
                @method('PUT')

                <div class="form-grid">
                    <div class="field">
                        <label class="field-label" for="cam-name">Nama kamera</label>
                        <input class="input-field" type="text" id="cam-name" name="name"
                            value="{{ old('name', $camera->name) }}" required>
                    </div>

                    <div class="field">
                        <label class="field-label" for="cam-type">Jenis siaran</label>
                        <select class="select-field" id="cam-type" name="stream_type" required>
                            @foreach ($types as $val => $label)
                                <option value="{{ $val }}" @selected(old('stream_type', $camera->stream_type) === $val)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field field-full">
                        <label class="field-label" for="cam-stream-url">Alamat URL siaran (Stream URL)</label>
                        <input class="input-field" type="text" id="cam-stream-url" name="stream_url"
                            value="{{ old('stream_url', $camera->stream_url) }}" required>
                        <p class="field-hint">
                            Contoh Web Stream: https://vmssamarinda.iconpln.co.id:...&format=fmp4... atau RTSP: rtsp://admin:pass@192.168.1.x:554/ch1
                        </p>
                    </div>

                    <div class="field field-full">
                        <label class="field-label" for="cam-snapshot-url">Alamat URL cuplikan potret (Opsional)</label>
                        <input class="input-field" type="text" id="cam-snapshot-url" name="snapshot_url"
                            value="{{ old('snapshot_url', $camera->snapshot_url) }}">
                    </div>

                    <div class="field">
                        <label class="field-label" for="cam-location">Letak / Lokasi kamera</label>
                        <input class="input-field" type="text" id="cam-location" name="location"
                            value="{{ old('location', $camera->location) }}">
                    </div>

                    <div class="field">
                        <label class="field-label" for="cam-notes">Catatan tambahan</label>
                        <input class="input-field" type="text" id="cam-notes" name="notes"
                            value="{{ old('notes', $camera->notes) }}">
                    </div>
                </div>

                <div class="form-check" style="margin-top: 14px;">
                    <label class="check-label">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $camera->is_active))>
                        <span>Aktifkan kamera ini untuk pemindai absen wajah</span>
                    </label>
                </div>

                <div id="test-result-box" class="alert" style="display: none; margin-top: 14px;">
                    <span id="test-result-text"></span>
                </div>

                <div class="form-actions" style="margin-top: 18px;">
                    <button class="btn btn-primary" type="submit">Simpan perubahan</button>
                    <button class="btn btn-outline" type="button" id="btn-uji-koneksi">Uji koneksi siaran</button>
                    <a href="{{ route('cameras.index') }}" class="btn btn-secondary">Batal</a>
                </div>
            </form>
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const btnUji = document.getElementById('btn-uji-koneksi');
            const typeInput = document.getElementById('cam-type');
            const streamInput = document.getElementById('cam-stream-url');
            const snapshotInput = document.getElementById('cam-snapshot-url');
            const resultBox = document.getElementById('test-result-box');
            const resultText = document.getElementById('test-result-text');

            if (btnUji) {
                btnUji.addEventListener('click', async function () {
                    const type = typeInput.value;
                    const streamUrl = streamInput.value.trim();
                    const snapshotUrl = snapshotInput.value.trim();

                    if (!streamUrl) {
                        alert('Silakan isi URL siaran terlebih dahulu.');
                        streamInput.focus();
                        return;
                    }

                    btnUji.disabled = true;
                    btnUji.textContent = 'Menguji koneksi...';
                    resultBox.style.display = 'block';
                    resultBox.className = 'alert alert-info';
                    resultText.textContent = 'Sedang menghubungi server siaran kamera, mohon tunggu beberapa detik...';

                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const token = tokenEl ? tokenEl.content : '';

                        const res = await fetch("{{ route('cameras.test') }}", {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                stream_type: type,
                                stream_url: streamUrl,
                                snapshot_url: snapshotUrl || null,
                            }),
                        });

                        const data = await res.json();
                        if (data.success) {
                            resultBox.className = 'alert alert-success';
                            resultText.textContent = data.message;
                        } else {
                            resultBox.className = 'alert alert-warning';
                            resultText.textContent = data.message;
                        }
                    } catch (e) {
                        resultBox.className = 'alert alert-warning';
                        resultText.textContent = 'Gagal menguji koneksi: periksa jaringan atau koneksi lokal.';
                    } finally {
                        btnUji.disabled = false;
                        btnUji.textContent = 'Uji koneksi siaran';
                    }
                });
            }
        });
    </script>
@endsection

