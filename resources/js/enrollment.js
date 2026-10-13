/* =========================================================
   Absen Solat SMKIM4
   Halaman pendaftaran wajah mandiri: mengambil foto lewat
   kamera, menyusun foto pilihan, dan menunggu antrean selesai.

   Berkas ini hanya dimuat di halaman pendaftaran wajah. Kamera
   absen di panel memakai berkas app.js dan sengaja dipisah,
   sebab keduanya punya keperluan yang berbeda: kamera absen
   mengirim satu bingkai lalu membaca jawaban server, sedangkan
   halaman ini mengumpulkan beberapa foto lebih dulu lalu
   mengirimnya sekaligus.
   ========================================================= */

const SHOOT_POLL_INTERVAL = 3000;

/* ---------- Menyusun daftar foto yang akan dikirim ---------- */

function setupPhotoStaging(form) {
    const input = form.querySelector("[data-shoot-input]");
    const preview = form.querySelector("[data-shoot-preview]");
    const counter = form.querySelector("[data-shoot-counter]");
    const note = form.querySelector("[data-shoot-note]");
    const submit = form.querySelector("[data-shoot-submit]");
    const max = Number(form.dataset.maxSubmit || 1);

    // Penyatuan hasil jepretan kamera dengan foto pilihan memakai DataTransfer.
    // Peramban yang tidak memilikinya tidak bisa menyusun foto satu per satu,
    // jadi pemilihan berkas biasa (yang tidak butuh DataTransfer) tetap dipakai
    // dan panel kameranya ditandai tidak bisa dipakai.
    const bisaMenempel = typeof DataTransfer !== "undefined";

    if (!input || !preview || !bisaMenempel) {
        return null;
    }

    const staged = [];
    const canvas = document.createElement("canvas");
    let busy = false;

    const key = (file) => `${file.name}|${file.size}|${file.lastModified}`;

    const sync = () => {
        const transfer = new DataTransfer();
        staged.forEach((file) => transfer.items.add(file));
        input.files = transfer.files;

        preview.innerHTML = "";

        staged.forEach((file, index) => {
            const card = document.createElement("div");
            card.className = "shot-card";

            const image = document.createElement("img");
            image.className = "shot-image";
            image.src = URL.createObjectURL(file);
            image.alt = `Foto pilihan ${index + 1}`;
            image.addEventListener(
                "load",
                () => URL.revokeObjectURL(image.src),
                { once: true },
            );

            const remove = document.createElement("button");
            remove.type = "button";
            remove.className = "btn btn-small btn-outline";
            remove.textContent = "Buang";
            remove.addEventListener("click", () => {
                staged.splice(index, 1);
                sync();
            });

            card.append(image, remove);
            preview.append(card);
        });

        if (counter) {
            counter.textContent =
                staged.length === 0
                    ? "Belum ada foto yang siap dikirim."
                    : `${staged.length} dari ${max} foto siap dikirim.`;
        }

        if (submit) {
            // Tombol kirim tetap mati selama masih ada foto yang diperiksa,
            // sebab mengirim dua kelompok sekaligus membuat pemeriksaan foto
            // kembar tidak bisa melihat kelompok yang lain.
            submit.disabled =
                staged.length === 0 || staged.length > max || busy;
        }
    };

    const add = (files) => {
        let ditolak = 0;

        Array.from(files).forEach((file) => {
            if (staged.length >= max) {
                ditolak++;
                return;
            }

            if (staged.some((ada) => key(ada) === key(file))) {
                return;
            }

            staged.push(file);
        });

        sync();

        return ditolak;
    };

    // Foto yang dipilih dari galeri disatukan dengan hasil jepretan kamera,
    // sebab keduanya dikirim lewat isian berkas yang sama. Tanpa penyatuan ini,
    // memilih foto dari galeri menghapus jepretan yang sudah diambil.
    input.addEventListener("change", () => {
        const ditolak = add(input.files);

        if (ditolak > 0 && note) {
            note.textContent = `Sekali kirim paling banyak ${max} foto. ${ditolak} foto terakhir tidak dipakai, kirim lagi setelah ini.`;
        }
    });

    sync();

    return {
        canvas,
        add,
        clear() {
            staged.splice(0, staged.length);
            sync();
        },
        setBusy(flag) {
            busy = flag;
            sync();
        },
    };
}

/* ---------- Kamera ---------- */

function setupShooter(root, staging) {
    const video = root.querySelector("[data-shoot-video]");
    const startButton = root.querySelector("[data-shoot-start]");
    const stopButton = root.querySelector("[data-shoot-stop]");
    const shootButton = root.querySelector("[data-shoot-capture]");
    const picker = root.querySelector("[data-shoot-facing]");
    const status = root.querySelector("[data-shoot-status]");
    const placeholder = root.querySelector("[data-shoot-placeholder]");

    if (!video || !startButton || !shootButton) {
        return;
    }

    let stream = null;
    let facing = "user";

    const say = (message) => {
        if (status) {
            status.textContent = message;
        }
    };

    const stop = () => {
        if (stream) {
            stream.getTracks().forEach((track) => track.stop());
            stream = null;
        }

        video.srcObject = null;
        shootButton.disabled = true;
        stopButton.disabled = true;
        startButton.disabled = false;

        if (placeholder) {
            placeholder.hidden = false;
        }

        say("Kamera dimatikan.");
    };

    const start = async () => {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            const blocked = root.querySelector("[data-shoot-blocked]");

            if (blocked) {
                blocked.hidden = false;
            }

            say(
                "Kamera tidak tersedia di peramban ini. Pakai pilihan foto dari galeri, hasilnya sama.",
            );

            return;
        }

        startButton.disabled = true;
        say("Menyalakan kamera.");

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: facing,
                    width: { ideal: 1280 },
                    height: { ideal: 960 },
                },
                audio: false,
            });
        } catch (error) {
            startButton.disabled = false;

            const ditolak =
                error &&
                (error.name === "NotAllowedError" ||
                    error.name === "SecurityError");

            say(
                ditolak
                    ? "Kamera diblokir. Izinkan akses kamera di peramban, atau pakai pilihan foto dari galeri."
                    : "Kamera tidak bisa dinyalakan. Pakai pilihan foto dari galeri, hasilnya sama.",
            );

            return;
        }

        video.srcObject = stream;

        try {
            await video.play();
        } catch (error) {
            // Peramban bisa menolak pemutaran otomatis; gambar tetap muncul
            // setelah pengguna menekan layar, jadi ini bukan kegagalan.
        }

        shootButton.disabled = false;
        stopButton.disabled = false;

        if (placeholder) {
            placeholder.hidden = true;
        }

        say("Kamera menyala. Ambil foto sesuai panduan di atas.");
    };

    const capture = () => {
        const width = video.videoWidth;
        const height = video.videoHeight;

        if (width === 0 || height === 0) {
            say("Gambar kamera belum siap. Tunggu sebentar, lalu ambil lagi.");

            return;
        }

        const scale = Math.min(1, 1280 / Math.max(width, height));
        staging.canvas.width = Math.round(width * scale);
        staging.canvas.height = Math.round(height * scale);

        const context = staging.canvas.getContext("2d");
        context.drawImage(
            video,
            0,
            0,
            staging.canvas.width,
            staging.canvas.height,
        );

        staging.canvas.toBlob(
            (blob) => {
                if (!blob) {
                    say("Foto gagal diambil. Coba lagi.");

                    return;
                }

                const file = new File([blob], `kamera-${Date.now()}.jpg`, {
                    type: "image/jpeg",
                });
                const rejected = staging.add([file]);

                if (rejected > 0) {
                    say(
                        "Jatah foto sekali kirim sudah penuh. Kirim dulu, lalu ambil foto berikutnya.",
                    );

                    return;
                }

                say(
                    "Foto diambil. Ambil foto berikutnya dengan sudut yang berbeda.",
                );
            },
            "image/jpeg",
            0.9,
        );
    };

    startButton.addEventListener("click", start);
    stopButton.addEventListener("click", stop);
    shootButton.addEventListener("click", capture);

    if (picker) {
        picker.addEventListener("change", async () => {
            facing = picker.value;

            if (stream) {
                stop();
                await start();
            }
        });
    }

    window.addEventListener("pagehide", stop);
}

/* ---------- Menunggu antrean selesai ---------- */

function setupQueueWatch(form, staging) {
    const alamat = form.dataset.progressUrl;

    if (!alamat) {
        return;
    }

    let terakhir = null;

    const check = async () => {
        let data = null;

        try {
            const response = await fetch(alamat, {
                headers: { "X-Requested-With": "XMLHttpRequest" },
                credentials: "same-origin",
            });

            if (!response.ok) {
                return;
            }

            data = await response.json();
        } catch (error) {
            // Jaringan terputus: keadaan terakhir dipertahankan. Mengunci
            // tombol hanya karena satu permintaan gagal membuat pemiliknya
            // tidak bisa mengirim apa pun.
            return;
        }

        const pending = Boolean(data && data.state && data.state.pending);

        if (pending === terakhir) {
            return;
        }

        terakhir = pending;

        // Dicari ulang setiap kali, sebab penanda ini berada di dalam wilayah
        // yang memutakhirkan dirinya sendiri dan unsur lamanya sudah diganti.
        const busy = document.querySelector("[data-shoot-busy]");

        if (busy) {
            busy.hidden = !pending;
        }

        staging.setBusy(pending);

        if (pending) {
            return;
        }

        // Antrean selesai: berkasnya sudah diterima server, jadi pilihan yang
        // tadi dikirim dikosongkan supaya tidak ikut terkirim dua kali.
        const input = form.querySelector("[data-shoot-input]");

        if (input && input.files.length > 0) {
            staging.clear();
        }
    };

    check();
    window.setInterval(check, SHOOT_POLL_INTERVAL);
}

/* ---------- Pendaftaran halaman ---------- */

function setupEnrollment() {
    document.querySelectorAll("[data-enrollment-form]").forEach((form) => {
        const staging = setupPhotoStaging(form);

        if (!staging) {
            // Peramban tanpa DataTransfer tidak bisa menyusun foto satu per
            // satu. Panel kamera ditandai tidak bisa dipakai supaya tidak ada
            // tombol yang tidak berguna, sedangkan pemilihan berkas biasa tetap
            // berjalan seperti isian berkas pada umumnya.
            document.querySelectorAll("[data-shoot]").forEach((root) => {
                const blocked = root.querySelector("[data-shoot-blocked]");

                if (blocked) {
                    blocked.hidden = false;
                }

                root.querySelectorAll(
                    "[data-shoot-start], [data-shoot-capture]",
                ).forEach((button) => {
                    button.disabled = true;
                });
            });

            return;
        }

        document
            .querySelectorAll("[data-shoot]")
            .forEach((root) => setupShooter(root, staging));

        setupQueueWatch(form, staging);
    });
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", setupEnrollment);
} else {
    setupEnrollment();
}
