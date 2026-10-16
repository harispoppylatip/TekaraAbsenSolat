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
    const listeners = [];
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

        listeners.forEach((fn) => {
            try {
                fn(staged.length);
            } catch (error) {
                // Ignore listener errors
            }
        });
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
        count() {
            return staged.length;
        },
        clear() {
            staged.splice(0, staged.length);
            sync();
        },
        setBusy(flag) {
            busy = flag;
            sync();
        },
        onChange(fn) {
            listeners.push(fn);
        },
    };
}

/* ---------- Kamera ---------- */

const AUTO_POSES = [
    { label: "Lurus", text: "Hadap lurus ke kamera" },
    { label: "Serong Kiri", text: "Tengok sedikit ke kiri" },
    { label: "Serong Kanan", text: "Tengok sedikit ke kanan" },
    { label: "Sedikit ke Atas", text: "Angkat kepala sedikit ke atas" },
    { label: "Sedikit ke Bawah", text: "Tundukkan kepala sedikit ke bawah" },
];

function setupShooter(root, staging) {
    const video = root.querySelector("[data-shoot-video]");
    const startButton = root.querySelector("[data-shoot-start]");
    const stopButton = root.querySelector("[data-shoot-stop]");
    const shootButton = root.querySelector("[data-shoot-capture]");
    const autoButton = root.querySelector("[data-shoot-auto]");
    const guideOverlay = root.querySelector("[data-shoot-guide]");
    const guideStep = root.querySelector("[data-shoot-guide-step]");
    const guideText = root.querySelector("[data-shoot-guide-text]");
    const guideCountdown = root.querySelector("[data-shoot-guide-countdown]");
    const flashEl = root.querySelector("[data-shoot-flash]");
    const picker = root.querySelector("[data-shoot-facing]");
    const status = root.querySelector("[data-shoot-status]");
    const placeholder = root.querySelector("[data-shoot-placeholder]");

    if (!video || !startButton || !shootButton) {
        return;
    }

    let stream = null;
    let facing = "user";
    let isAutoRunning = false;
    let autoTimer = null;

    const say = (message) => {
        if (status) {
            status.textContent = message;
        }
    };

    const getQuotaInfo = () => {
        const required = Number(root.dataset.required || 5);
        const usable = Number(root.dataset.usable || 0);
        const needed = Math.max(0, required - usable);
        const currentStaged = staging.count ? staging.count() : 0;
        const maxPerSubmit = Number(root.dataset.shoot || 5);
        const remainingToSubmit = Math.max(0, maxPerSubmit - currentStaged);
        const remainingNeeded = Math.max(0, needed - currentStaged);
        const targetCount = Math.min(remainingNeeded, remainingToSubmit, AUTO_POSES.length);

        return {
            required,
            usable,
            needed,
            currentStaged,
            maxPerSubmit,
            remainingNeeded,
            targetCount,
        };
    };

    const updateAutoButtonText = () => {
        if (!autoButton) return;

        if (isAutoRunning) {
            autoButton.textContent = "Batal foto otomatis";
            return;
        }

        const { required, usable, needed, currentStaged, targetCount } = getQuotaInfo();

        if (usable >= required) {
            autoButton.textContent = "Foto wajah sudah lengkap";
            autoButton.disabled = true;
            return;
        }

        if (targetCount === 0 && currentStaged > 0) {
            autoButton.textContent = `Foto siap dikirim (${currentStaged})`;
            autoButton.disabled = !stream;
            return;
        }

        if (targetCount === 5) {
            autoButton.textContent = "Mulai foto otomatis (5 sudut)";
        } else {
            autoButton.textContent = `Mulai foto otomatis (${targetCount} foto lagi)`;
        }

        autoButton.disabled = !stream;
    };

    const triggerFlash = () => {
        if (!flashEl) return;
        flashEl.hidden = false;
        flashEl.classList.add("flash-active");
        setTimeout(() => {
            flashEl.classList.remove("flash-active");
            setTimeout(() => {
                flashEl.hidden = true;
            }, 150);
        }, 120);
    };

    const wait = (ms) => new Promise((resolve) => {
        autoTimer = setTimeout(resolve, ms);
    });

    const stopAuto = () => {
        isAutoRunning = false;
        if (autoTimer) {
            clearTimeout(autoTimer);
            autoTimer = null;
        }

        if (guideOverlay) {
            guideOverlay.hidden = true;
            guideOverlay.classList.remove("is-active", "is-snapped");
        }
        if (guideCountdown) {
            guideCountdown.hidden = true;
        }

        updateAutoButtonText();

        if (shootButton) {
            shootButton.disabled = !stream;
        }
        if (startButton) {
            startButton.disabled = !!stream;
        }
        if (stopButton) {
            stopButton.disabled = !stream;
        }
    };

    const stop = () => {
        if (isAutoRunning) {
            stopAuto();
        }

        if (stream) {
            stream.getTracks().forEach((track) => track.stop());
            stream = null;
        }

        video.srcObject = null;
        shootButton.disabled = true;
        updateAutoButtonText();
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
        const isUserFacing = facing === "user";
        video.style.transform = isUserFacing ? "scaleX(-1)" : "none";

        try {
            await video.play();
        } catch (error) {
            // Peramban bisa menolak pemutaran otomatis; gambar tetap muncul
            // setelah pengguna menekan layar, jadi ini bukan kegagalan.
        }

        shootButton.disabled = false;
        updateAutoButtonText();
        stopButton.disabled = false;

        if (placeholder) {
            placeholder.hidden = true;
        }

        const btnLabel = autoButton ? autoButton.textContent : "Mulai foto otomatis";
        say(`Kamera menyala. Tekan '${btnLabel}' untuk pengambilan otomatis.`);
    };

    const takeSnapshot = (customLabel = null) => {
        return new Promise((resolve) => {
            const width = video.videoWidth;
            const height = video.videoHeight;

            if (width === 0 || height === 0) {
                say("Gambar kamera belum siap. Tunggu sebentar.");
                resolve(false);
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

            triggerFlash();

            if (guideOverlay) {
                guideOverlay.classList.add("is-snapped");
                setTimeout(() => guideOverlay.classList.remove("is-snapped"), 300);
            }

            staging.canvas.toBlob(
                (blob) => {
                    if (!blob) {
                        say("Foto gagal diambil. Coba lagi.");
                        resolve(false);
                        return;
                    }

                    const suffix = customLabel
                        ? `-${customLabel.toLowerCase().replace(/\s+/g, "-")}`
                        : "";
                    const file = new File([blob], `kamera${suffix}-${Date.now()}.jpg`, {
                        type: "image/jpeg",
                    });
                    const rejected = staging.add([file]);

                    if (rejected > 0) {
                        say(
                            "Jatah foto sekali kirim sudah penuh. Kirim dulu, lalu ambil foto berikutnya.",
                        );
                        resolve(false);
                        return;
                    }

                    resolve(true);
                },
                "image/jpeg",
                0.9,
            );
        });
    };

    const captureManual = async () => {
        const ok = await takeSnapshot();
        if (ok) {
            say("Foto diambil. Ambil foto berikutnya dengan sudut yang berbeda.");
        }
    };

    const runAutoCapture = async () => {
        if (isAutoRunning) {
            stopAuto();
            say("Pengambilan foto otomatis dibatalkan.");
            return;
        }

        if (!stream) {
            await start();
            if (!stream) return;
        }

        const { required, usable, needed, currentStaged, maxPerSubmit, targetCount } = getQuotaInfo();

        if (usable >= required) {
            say(`Foto wajah Anda sudah lengkap (${usable} dari ${required} foto). Tidak perlu mengambil foto lagi.`);
            return;
        }

        if (targetCount <= 0) {
            if (currentStaged >= needed) {
                say(`Sudah ada ${currentStaged} foto siap dikirim (cukup untuk melengkapi foto yang kurang). Periksa di bawah lalu kirim.`);
            } else if (currentStaged >= maxPerSubmit) {
                say(`Jatah foto sekali kirim sudah penuh (${currentStaged} foto). Kirim foto yang ada terlebih dahulu.`);
            }
            return;
        }

        isAutoRunning = true;
        if (autoButton) {
            autoButton.textContent = "Batal foto otomatis";
        }
        if (shootButton) {
            shootButton.disabled = true;
        }

        if (guideOverlay) {
            guideOverlay.hidden = false;
            guideOverlay.classList.add("is-active");
        }

        for (let i = 0; i < targetCount; i++) {
            if (!isAutoRunning) break;

            const pose = AUTO_POSES[i];
            const currentStep = `${i + 1}/${targetCount}`;
            if (guideStep) guideStep.textContent = currentStep;
            if (guideText) guideText.textContent = pose.text;
            say(`Langkah ${currentStep}: ${pose.text}`);

            if (guideCountdown) {
                guideCountdown.hidden = false;
            }

            for (let c = 3; c >= 1; c--) {
                if (!isAutoRunning) break;
                if (guideCountdown) guideCountdown.textContent = c;
                await wait(1000);
            }

            if (!isAutoRunning) break;
            if (guideCountdown) guideCountdown.hidden = true;

            const success = await takeSnapshot(pose.label);
            if (!success) {
                break;
            }

            if (i < targetCount - 1) {
                say(`Bagus! Bersiap untuk pose berikutnya...`);
                await wait(900);
            }
        }

        if (isAutoRunning) {
            if (targetCount === 5) {
                say("Selesai! 5 foto dari 5 sudut berhasil diambil. Periksa pilihan foto di bawah lalu kirim.");
            } else {
                say(`Selesai! ${targetCount} foto berhasil diambil. Periksa pilihan foto di bawah lalu kirim.`);
            }
            stopAuto();
        }
    };

    startButton.addEventListener("click", start);
    stopButton.addEventListener("click", stop);
    shootButton.addEventListener("click", captureManual);
    if (autoButton) {
        autoButton.addEventListener("click", runAutoCapture);
    }

    if (picker) {
        picker.addEventListener("change", async () => {
            facing = picker.value;

            if (stream) {
                stop();
                await start();
            }
        });
    }

    staging.onChange(() => {
        updateAutoButtonText();
    });

    root.addEventListener("enrollment:quota-changed", () => {
        updateAutoButtonText();
    });

    updateAutoButtonText();

    window.addEventListener("pagehide", stop);
}

/* ---------- Menunggu antrean selesai ---------- */

function setupQueueWatch(form, staging) {
    const alamat = form.dataset.progressUrl;

    if (!alamat) {
        return;
    }

    let terakhir = null;
    let stateSignature = null;
    // Penanda waktu batch selesai untuk auto-dismiss setelah 15 detik
    const batchDismissTimers = {};

    const dismissBatchArticle = (batchId) => {
        const article = document.querySelector(`[data-batch="${batchId}"]`);
        if (article) {
            const card = article.closest("[data-progress-card]");
            article.style.transition = "opacity 0.5s";
            article.style.opacity = "0";
            setTimeout(() => {
                if (article.parentNode) {
                    article.parentNode.removeChild(article);
                }
                if (
                    card &&
                    card.querySelectorAll("[data-batch]").length === 0 &&
                    card.parentNode
                ) {
                    card.parentNode.removeChild(card);
                }
            }, 500);
        }
        delete batchDismissTimers[batchId];
    };

    const scheduleAutoDismiss = (batchId) => {
        // Jangan jadwalkan ulang kalau sudah ada timer
        if (batchDismissTimers[batchId]) {
            return;
        }
        batchDismissTimers[batchId] = setTimeout(() => {
            dismissBatchArticle(batchId);
        }, 15000);
    };

    const cancelAutoDismiss = (batchId) => {
        if (batchDismissTimers[batchId]) {
            clearTimeout(batchDismissTimers[batchId]);
            delete batchDismissTimers[batchId];
        }
    };

    const syncLiveRegionsNow = async () => {
        const regions = Array.from(document.querySelectorAll("[data-live]"));
        if (regions.length === 0) {
            return;
        }

        try {
            const response = await fetch(window.location.href, {
                headers: {
                    "X-Live-Refresh": "1",
                    "X-Requested-With": "XMLHttpRequest",
                },
                credentials: "same-origin",
            });

            if (!response.ok) {
                return;
            }

            const html = await response.text();
            const parsed = new DOMParser().parseFromString(html, "text/html");

            regions.forEach((region) => {
                const key = region.dataset.live;
                const incoming = parsed.querySelector(`[data-live="${key}"]`);

                if (!incoming || incoming.innerHTML === region.innerHTML) {
                    return;
                }

                const openMap = {};
                region.querySelectorAll("details").forEach((el, idx) => {
                    openMap[el.dataset.record || `idx-${idx}`] = el.open;
                });

                region.innerHTML = incoming.innerHTML;

                region.querySelectorAll("details").forEach((el, idx) => {
                    const k = el.dataset.record || `idx-${idx}`;
                    if (openMap[k]) {
                        el.open = true;
                    }
                });
            });
        } catch (error) {
            // Abaikan kegagalan jaringan sementara
        }
    };

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

        const state = (data && data.state) || {};
        const pending = Boolean(state.pending);
        const batches = Array.isArray(data && data.batches)
            ? data.batches
            : Array.isArray(state.batches)
              ? state.batches
              : [];

        // Periksa setiap batch: yang baru selesai (done=true) jadwalkan auto-dismiss;
        // yang masih berjalan batalkan auto-dismiss agar tidak dihapus lebih awal.
        batches.forEach((batch) => {
            if (batch.done) {
                scheduleAutoDismiss(batch.id);
            } else {
                cancelAutoDismiss(batch.id);
            }
        });

        const nextSignature = JSON.stringify({
            status: state.status,
            usable: state.usable,
            total: state.total,
            pending: pending,
            finished: Boolean(state.finished),
            locked: Boolean(state.locked),
            batches: batches.map((b) => `${b.id}:${b.finished}:${b.done}`),
        });

        if (state) {
            document.querySelectorAll("[data-shoot]").forEach((root) => {
                if (typeof state.usable !== "undefined") {
                    root.dataset.usable = state.usable;
                }
                if (typeof state.required !== "undefined") {
                    root.dataset.required = state.required;
                }
                const req = Number(root.dataset.required || 5);
                const use = Number(root.dataset.usable || 0);
                const needed = Math.max(0, req - use);
                root.dataset.needed = needed;

                const inst = root.closest(".tool-body")?.querySelector("[data-shoot-instruction] strong");
                if (inst) {
                    inst.textContent = needed === 5
                        ? "Mulai foto otomatis (5 sudut)"
                        : (needed > 0 ? `Mulai foto otomatis (${needed} foto lagi)` : "Foto sudah lengkap");
                }

                root.dispatchEvent(new CustomEvent("enrollment:quota-changed"));
            });
        }

        if (stateSignature !== null && stateSignature !== nextSignature) {
            await syncLiveRegionsNow();
        }
        stateSignature = nextSignature;

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
