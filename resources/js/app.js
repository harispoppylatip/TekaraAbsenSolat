/* =========================================================
   Absen Solat SMKIM4
   Menangani menu samping, penyegaran wilayah halaman, kamera
   absen, unggah berkas wajah, kemajuan pemrosesan wajah, dan
   tombol cetak.
   ========================================================= */

const LIVE_INTERVAL = 5000;

function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');

    return meta ? meta.getAttribute("content") : "";
}

// Penanda satu kali impor supaya seluruh berkas dalam satu kiriman
// menghuni kelompok kemajuan yang sama.
function newBatchId() {
    if (window.crypto && typeof window.crypto.randomUUID === "function") {
        return window.crypto.randomUUID();
    }

    return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (letter) => {
        const value = Math.floor(Math.random() * 16);
        const digit = letter === "x" ? value : (value & 0x3) | 0x8;

        return digit.toString(16);
    });
}

/* ---------- Menu samping ---------- */

function setupDrawer() {
    const shell = document.querySelector(".app-shell");

    if (!shell) {
        return;
    }

    const toggle = document.querySelector(".menu-toggle");
    const backdrop = document.querySelector(".sidebar-backdrop");

    const sync = () => {
        if (window.innerWidth <= 1080) {
            document.body.classList.add("drawer-ready");
        } else {
            document.body.classList.remove("drawer-ready", "drawer-open");
        }
    };

    const open = () => document.body.classList.add("drawer-open");
    const close = () => document.body.classList.remove("drawer-open");

    if (toggle) {
        toggle.addEventListener("click", () => {
            if (document.body.classList.contains("drawer-open")) {
                close();
                toggle.textContent = "Menu";
                toggle.setAttribute("aria-expanded", "false");
            } else {
                open();
                toggle.textContent = "Tutup";
                toggle.setAttribute("aria-expanded", "true");
            }
        });
    }

    if (backdrop) {
        backdrop.addEventListener("click", () => {
            close();

            if (toggle) {
                toggle.textContent = "Menu";
                toggle.setAttribute("aria-expanded", "false");
            }
        });
    }

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            close();
        }
    });

    sync();
    window.addEventListener("resize", sync);
}

/* ---------- Penyegaran wilayah halaman ---------- */

function detailState(scope) {
    const state = {};

    scope.querySelectorAll("details").forEach((element, index) => {
        state[element.dataset.record || `index-${index}`] = element.open;
    });

    return state;
}

function restoreDetailState(scope, state) {
    scope.querySelectorAll("details").forEach((element, index) => {
        const key = element.dataset.record || `index-${index}`;

        if (state[key]) {
            element.open = true;
        }
    });
}

// Kotak centang pilihan baris dikunci dengan nama dan nilainya supaya centang
// tidak hilang saat wilayah halaman menyegarkan diri sendiri.
function checkboxKey(input) {
    return `${input.name}|${input.value}`;
}

function checkedState(scope) {
    const state = {};

    scope.querySelectorAll('input[type="checkbox"][name]').forEach((input) => {
        state[checkboxKey(input)] = input.checked;
    });

    return state;
}

function restoreCheckedState(scope, state) {
    scope.querySelectorAll('input[type="checkbox"][name]').forEach((input) => {
        const key = checkboxKey(input);

        if (Object.prototype.hasOwnProperty.call(state, key)) {
            input.checked = state[key];
        }
    });
}

async function refreshLiveRegions() {
    const regions = Array.from(document.querySelectorAll("[data-live]"));

    if (regions.length === 0) {
        return;
    }

    let html = null;

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

        html = await response.text();
    } catch (error) {
        return;
    }

    const parsed = new DOMParser().parseFromString(html, "text/html");

    regions.forEach((region) => {
        const key = region.dataset.live;
        const incoming = parsed.querySelector(`[data-live="${key}"]`);

        if (!incoming || incoming.innerHTML === region.innerHTML) {
            return;
        }

        const state = detailState(region);
        const checks = checkedState(region);
        region.innerHTML = incoming.innerHTML;
        restoreDetailState(region, state);
        restoreCheckedState(region, checks);
        setupCameras(region);
        setupBulkUpload(region);
    });
}

function setupLiveRegions() {
    if (document.querySelector("[data-live]") === null) {
        return;
    }

    window.setInterval(refreshLiveRegions, LIVE_INTERVAL);
}

/* ---------- Tombol cetak dan penghapusan ---------- */

function setupPrintButtons() {
    document.querySelectorAll("[data-print]").forEach((button) => {
        button.addEventListener("click", (event) => {
            event.preventDefault();
            window.print();
        });
    });
}

function setupConfirms() {
    document.addEventListener("submit", (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const message = form.dataset.confirm;

        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });
}

/* ---------- Saringan langsung ---------- */

function setupAutoSubmit() {
    document.querySelectorAll("[data-auto-submit]").forEach((field) => {
        field.addEventListener("change", () => {
            const form = field.closest("form");

            if (form) {
                form.submit();
            }
        });
    });
}

/* ---------- Kamera absen ---------- */

function setupCameras(scope = document) {
    scope.querySelectorAll("[data-camera]").forEach((root) => {
        if (root.dataset.cameraReady === "1") {
            return;
        }

        root.dataset.cameraReady = "1";
        initCamera(root);
    });
}

// Pilihan kamera disimpan sebagai satu nilai: arah hadap ("facing:user")
// atau perangkat tertentu ("device:<id>"). Cara ini membuat ponsel bisa
// berpindah antara kamera depan dan belakang, sedangkan komputer bisa
// memilih kamera tambahan yang terpasang.
const CAMERA_FACING_OPTIONS = [
    ["facing:user", "Kamera depan"],
    ["facing:environment", "Kamera belakang"],
];

function cameraConstraint(value) {
    if (typeof value === "string" && value.startsWith("device:")) {
        return { deviceId: { exact: value.slice(7) } };
    }

    if (typeof value === "string" && value.startsWith("facing:")) {
        return { facingMode: value.slice(7) };
    }

    return { facingMode: "user" };
}

// Nama kamera baru terbaca peramban setelah izin diberikan, jadi daftar
// perangkat hanya bisa dilengkapi setelah kamera pernah dinyalakan.
async function cameraDevices() {
    if (
        !navigator.mediaDevices ||
        typeof navigator.mediaDevices.enumerateDevices !== "function"
    ) {
        return [];
    }

    try {
        const devices = await navigator.mediaDevices.enumerateDevices();

        return devices.filter(
            (device) => device.kind === "videoinput" && device.label,
        );
    } catch (error) {
        return [];
    }
}

function buildCameraChoices(picker, cameras, preferred) {
    picker.innerHTML = "";

    const facingGroup = document.createElement("optgroup");
    facingGroup.label = "Arah kamera";

    CAMERA_FACING_OPTIONS.forEach(([value, label]) => {
        const option = document.createElement("option");
        option.value = value;
        option.textContent = label;
        facingGroup.appendChild(option);
    });

    picker.appendChild(facingGroup);

    if (cameras.length > 0) {
        const deviceGroup = document.createElement("optgroup");
        deviceGroup.label = "Kamera yang terdeteksi";

        cameras.forEach((camera, index) => {
            const option = document.createElement("option");
            option.value = `device:${camera.deviceId}`;
            option.textContent = camera.label || `Kamera ${index + 1}`;
            deviceGroup.appendChild(option);
        });

        picker.appendChild(deviceGroup);
    }

    // Pilihan lama dipertahankan bila masih tersedia, supaya daftar yang
    // diperbarui tidak diam-diam memindahkan kamera yang sedang dipakai.
    const options = Array.from(picker.options);
    const chosen = preferred
        .filter((value) => value)
        .find((value) => options.some((option) => option.value === value));

    picker.value = chosen || CAMERA_FACING_OPTIONS[0][0];

    return picker.value;
}

function initCamera(root) {
    const video = root.querySelector("[data-camera-video]");
    const canvas = root.querySelector("[data-camera-canvas]");
    const startButton = root.querySelector("[data-camera-start]");
    const stopButton = root.querySelector("[data-camera-stop]");
    const report = root.querySelector("[data-camera-report]");
    const status = root.querySelector("[data-camera-status]");
    const placeholder = root.querySelector("[data-camera-placeholder]");
    const picker = root.querySelector("[data-camera-facing]");
    const endpoint = root.dataset.endpoint;
    const interval = Number(root.dataset.interval || 1500);

    let stream = null;
    let timer = null;
    let busy = false;
    let lastMessage = "";
    let facing =
        picker && picker.value ? picker.value : CAMERA_FACING_OPTIONS[0][0];
    let starting = false;

    // Nomor urut percobaan menyalakan kamera. Izin kamera bisa lama diminta
    // peramban, jadi hasil percobaan lama tidak boleh lagi mengubah tombol,
    // teks status, maupun pencatatan bila kamera sudah dimatikan atau diganti.
    let attempt = 0;

    function say(text) {
        if (status) {
            status.textContent = text;
        }
    }

    function addLine(message, ok, photoUrl) {
        if (!report) {
            return;
        }

        const line = document.createElement("p");
        const time = new Date().toLocaleTimeString("id-ID", {
            hour: "2-digit",
            minute: "2-digit",
            second: "2-digit",
        });

        line.className = `report-line ${ok ? "is-saved" : "is-failed"}`;
        line.textContent = `${time} ${message}`;

        // Foto bukti yang baru tersimpan ditampilkan sekalian supaya petugas
        // bisa langsung memastikan orang yang tercatat memang yang di depan
        // kamera. Potretnya dibuka penuh di tab baru.
        if (ok && photoUrl) {
            const link = document.createElement("a");
            link.className = "proof-link report-photo";
            link.href = photoUrl;
            link.target = "_blank";
            link.rel = "noopener";
            link.title = "Buka foto bukti absen";

            const image = document.createElement("img");
            image.className = "proof-thumb";
            image.src = photoUrl;
            image.alt = "Foto bukti absen";
            image.width = 44;
            image.height = 44;
            image.loading = "lazy";
            image.decoding = "async";

            link.append(image);
            line.classList.add("report-line-photo");
            line.append(link);
        }

        report.prepend(line);

        while (report.children.length > 8) {
            report.removeChild(report.lastElementChild);
        }
    }

    // Perbarui daftar pilihan kamera tanpa mengubah kamera yang sedang dipakai.
    async function refreshChoices() {
        if (!picker) {
            return;
        }

        const cameras = await cameraDevices();

        facing = buildCameraChoices(picker, cameras, [facing, picker.value]);
    }

    async function start() {
        if (stream !== null) {
            return;
        }

        if (picker && picker.value) {
            facing = picker.value;
        }

        const mine = ++attempt;

        starting = true;

        // Tombol ditukar sebelum menunggu izin peramban supaya penyalaan bisa
        // dibatalkan selagi izinnya belum dijawab.
        if (startButton) {
            startButton.disabled = true;
        }

        if (stopButton) {
            stopButton.disabled = false;
        }

        say("Menyalakan kamera. Izinkan akses kamera bila peramban meminta.");

        let opened = null;

        try {
            opened = await navigator.mediaDevices.getUserMedia({
                video: Object.assign(
                    { width: { ideal: 640 } },
                    cameraConstraint(facing),
                ),
                audio: false,
            });
        } catch (error) {
            opened = null;
        }

        // Kamera sudah dimatikan atau diganti selagi izin diminta.
        if (mine !== attempt) {
            if (opened) {
                opened.getTracks().forEach((track) => track.stop());
            }

            return;
        }

        starting = false;

        if (opened === null) {
            if (startButton) {
                startButton.disabled = false;
            }

            if (stopButton) {
                stopButton.disabled = true;
            }

            say(
                "Kamera tidak bisa dinyalakan. Periksa izin kamera pada peramban lalu coba lagi.",
            );

            return;
        }

        stream = opened;
        video.srcObject = stream;
        video.hidden = false;

        if (placeholder) {
            placeholder.hidden = true;
        }

        // Tidak ditunggu. Peramban baru menyelesaikan play() setelah gambar
        // pertama tiba, jadi menunggunya bisa membuat tombol dan teks status
        // tertahan lama walau siaran sudah siap.
        video.play().catch(() => {});

        if (startButton) {
            startButton.disabled = true;
        }

        if (stopButton) {
            stopButton.disabled = false;
        }

        say(
            "Kamera menyala. Hadapkan wajah ke kamera, pencatatan berjalan sendiri.",
        );

        timer = window.setInterval(capture, interval);

        // Nama kamera baru terbaca lengkap setelah izin diberikan.
        await refreshChoices();
    }

    function closeStream() {
        // Setiap siaran yang ditutup membatalkan percobaan menyalakan yang
        // masih berjalan, termasuk yang sedang menunggu izin peramban.
        attempt += 1;

        if (timer !== null) {
            window.clearInterval(timer);
            timer = null;
        }

        if (stream !== null) {
            stream.getTracks().forEach((track) => track.stop());
            stream = null;
        }

        if (video) {
            video.srcObject = null;
            video.hidden = true;
        }

        if (placeholder) {
            placeholder.hidden = false;
        }
    }

    function stop() {
        closeStream();

        if (startButton) {
            startButton.disabled = false;
        }

        if (stopButton) {
            stopButton.disabled = true;
        }

        say("Kamera dimatikan.");
    }

    async function capture() {
        if (busy || stream === null || !canvas) {
            return;
        }

        busy = true;

        try {
            const width = video.videoWidth || 480;
            const height = video.videoHeight || 360;

            canvas.width = width;
            canvas.height = height;
            canvas.getContext("2d").drawImage(video, 0, 0, width, height);

            const blob = await new Promise((resolve) =>
                canvas.toBlob(resolve, "image/jpeg", 0.85),
            );

            if (!blob) {
                return;
            }

            const body = new FormData();
            body.append("photo", blob, "absen.jpg");

            const response = await fetch(endpoint, {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": csrfToken(),
                    Accept: "application/json",
                },
                credentials: "same-origin",
                body,
            });

            const data = await response.json().catch(() => null);

            if (!data) {
                addLine("Balasan server tidak terbaca.", false);

                return;
            }

            const message =
                data.message || data.display_message || "Tidak ada keterangan.";
            const ok = data.ok === true;

            if (!ok && message === lastMessage) {
                return;
            }

            lastMessage = ok ? "" : message;
            addLine(message, ok, data.proof_url || "");
        } catch (error) {
            addLine("Sambungan ke server terputus.", false);
        } finally {
            busy = false;
        }
    }

    if (startButton) {
        startButton.disabled = false;
        startButton.addEventListener("click", start);
    }

    if (stopButton) {
        stopButton.disabled = true;
        stopButton.addEventListener("click", stop);
    }

    // Berganti kamera saat siaran berjalan, atau saat izinnya sedang diminta,
    // berarti membuka ulang perangkatnya.
    if (picker) {
        picker.addEventListener("change", async () => {
            facing = picker.value;

            if (stream === null && !starting) {
                return;
            }

            closeStream();
            await start();
        });
    }

    if (
        navigator.mediaDevices &&
        typeof navigator.mediaDevices.addEventListener === "function"
    ) {
        navigator.mediaDevices.addEventListener("devicechange", () => {
            refreshChoices();
        });
    }

    window.addEventListener("pagehide", stop);
}

/* ---------- Unggah banyak berkas wajah ---------- */

function setupBulkUpload(scope = document) {
    scope.querySelectorAll("[data-bulk-upload]").forEach((root) => {
        if (root.dataset.bulkReady === "1") {
            return;
        }

        root.dataset.bulkReady = "1";

        const input = root.querySelector("[data-bulk-input]");
        const button = root.querySelector("[data-bulk-start]");
        const stopButton = root.querySelector("[data-bulk-stop]");
        const report = root.querySelector("[data-bulk-report]");
        const summary = root.querySelector("[data-bulk-summary]");
        const endpoint = root.dataset.endpoint;
        const field = root.dataset.field || "folder";
        const ownerField = root.querySelector("[data-bulk-owner]");

        let running = false;
        let batchId = "";
        const tally = { queued: 0, skipped: 0 };

        function writeReport(text, ok) {
            if (!report) {
                return;
            }

            const line = document.createElement("p");

            line.className = `report-line ${ok ? "is-saved" : "is-failed"}`;
            line.textContent = text;
            report.prepend(line);

            while (report.children.length > 30) {
                report.removeChild(report.lastElementChild);
            }
        }

        function writeSummary() {
            if (summary) {
                summary.textContent = `${tally.queued} foto masuk antrean pemrosesan, ${tally.skipped} foto dilewati.`;
            }
        }

        async function send(file) {
            const body = new FormData();
            body.append("photo", file, file.name);

            if (batchId) {
                body.append("batch_id", batchId);
            }

            if (field === "folder") {
                const parts = (file.webkitRelativePath || "").split("/");
                body.append("folder", parts.length > 1 ? parts[0] : "");
            } else if (ownerField) {
                body.append("identifier_number", ownerField.value);
            }

            const response = await fetch(endpoint, {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": csrfToken(),
                    Accept: "application/json",
                },
                credentials: "same-origin",
                body,
            });

            return response.json().catch(() => null);
        }

        async function run() {
            if (running || !input) {
                return;
            }

            const files = Array.from(input.files || []);

            if (files.length === 0) {
                writeReport("Belum ada berkas yang dipilih.", false);

                return;
            }

            running = true;
            batchId = newBatchId();

            if (button) {
                button.disabled = true;
            }

            if (stopButton) {
                stopButton.disabled = false;
            }

            tally.queued = 0;
            tally.skipped = 0;

            for (const file of files) {
                if (!running) {
                    writeReport("Pengunggahan dihentikan.", false);
                    break;
                }

                try {
                    const data = await send(file);

                    if (data && data.queued) {
                        tally.queued += 1;
                        writeReport(
                            `${file.name}: ${data.message || "masuk antrean pemrosesan"}`,
                            true,
                        );
                        refreshFaceProgress();
                    } else {
                        tally.skipped += 1;
                        writeReport(
                            `${file.name}: ${(data && data.message) || "dilewati"}`,
                            false,
                        );
                    }
                } catch (error) {
                    tally.skipped += 1;
                    writeReport(`${file.name}: sambungan terputus`, false);
                }

                writeSummary();
            }

            running = false;

            if (button) {
                button.disabled = false;
            }

            if (stopButton) {
                stopButton.disabled = true;
            }

            writeSummary();
        }

        if (button) {
            button.disabled = true;
            button.addEventListener("click", run);
        }

        if (stopButton) {
            stopButton.disabled = true;
            stopButton.addEventListener("click", () => {
                running = false;
            });
        }

        if (input) {
            input.addEventListener("change", () => {
                const count = (input.files || []).length;

                if (button) {
                    button.disabled = count === 0;
                }

                if (summary) {
                    summary.textContent = `${count} berkas dipilih.`;
                }
            });
        }
    });
}

/* ---------- Kemajuan pendaftaran wajah ---------- */

let faceProgressTick = null;

function refreshFaceProgress() {
    if (typeof faceProgressTick === "function") {
        faceProgressTick();
    }
}

function textLine(className, text) {
    const line = document.createElement("p");

    line.className = className;
    line.textContent = text;

    return line;
}

function batchTone(batch) {
    if (!batch.done) {
        return "berjalan";
    }

    return batch.failed > 0 ? "sebagian" : "selesai";
}

function updateBatchAlert(item, batch) {
    const alert = item.querySelector("[data-batch-alert]");

    if (!batch.stalled) {
        if (alert) {
            alert.remove();
        }

        return;
    }

    if (alert) {
        return;
    }

    const line = textLine(
        "queue-note queue-alert",
        "Antrean belum dijalankan. Jalankan php artisan queue:work di server supaya foto yang menunggu mulai diproses.",
    );

    line.dataset.batchAlert = "1";

    const form = item.querySelector("[data-batch-retry]");

    if (form) {
        form.before(line);
    } else {
        item.append(line);
    }
}

function updateBatchRetry(item, batch, retryUrl) {
    const form = item.querySelector("[data-batch-retry]");
    const count = batch.retryable || 0;

    if (count === 0) {
        if (form) {
            form.remove();
        }

        return;
    }

    const label = `Ulangi ${count} foto yang gagal`;

    if (form) {
        const button = form.querySelector("button");

        if (button) {
            button.textContent = label;
        }

        return;
    }

    const created = document.createElement("form");

    created.method = "POST";
    created.action = retryUrl;
    created.dataset.batchRetry = "1";

    const token = document.createElement("input");

    token.type = "hidden";
    token.name = "_token";
    token.value = csrfToken();

    const button = document.createElement("button");

    button.type = "submit";
    button.className = "btn btn-small btn-secondary";
    button.textContent = label;

    created.append(token, button);
    item.append(created);
}

function buildBatch(item, batch, retryUrl) {
    item.className = "queue-item";
    item.dataset.batch = batch.id;
    item.dataset.tone = batchTone(batch);

    const head = document.createElement("div");

    head.className = "queue-head";

    const identity = document.createElement("div");

    identity.append(textLine("queue-title", batch.member));
    identity.append(textLine("queue-note", batch.member_note));

    const percent = textLine("queue-value", `${batch.percent}%`);

    percent.dataset.batchPercent = "1";
    head.append(identity, percent);

    const note = textLine("queue-note", batch.note);

    note.dataset.batchNote = "1";

    const track = document.createElement("div");

    track.className = "progress-track";

    const fill = document.createElement("div");

    fill.className = "progress-fill";
    fill.dataset.batchBar = "1";
    fill.style.width = `${batch.percent}%`;
    track.append(fill);

    const rows = document.createElement("div");

    rows.className = "queue-rows";

    batch.rows.forEach((row) => {
        const line = document.createElement("div");

        line.className = "queue-row";
        line.dataset.row = row.id;
        line.dataset.tone = row.status;
        line.append(textLine("queue-file", row.name));
        line.append(textLine("queue-note", row.note));
        rows.append(line);
    });

    item.append(head, note, track, rows);
    updateBatchAlert(item, batch);
    updateBatchRetry(item, batch, retryUrl);
}

/**
 * Kelompok foto yang sudah selesai dihilangkan sendiri oleh server setelah
 * waktu tunggunya habis. Peramban mengikuti saat yang ditentukan server itu
 * supaya kartunya hilang tepat waktu, tidak menunggu penyegaran berikutnya.
 */
function scheduleBatchRemoval(root, list, item, batch) {
    if (!batch.remove_at || item.dataset.removeAt === batch.remove_at) {
        return;
    }

    item.dataset.removeAt = batch.remove_at;

    const left = new Date(batch.remove_at).getTime() - Date.now();

    window.setTimeout(
        () => {
            item.remove();
            root.hidden = list.querySelector("[data-batch]") === null;
        },
        Math.max(0, left),
    );
}

function applyFaceProgress(root, list, data) {
    const batches = Array.isArray(data.batches) ? data.batches : [];
    const template = root.dataset.retryTemplate || "";
    const shown = new Set(batches.map((batch) => batch.id));

    root.hidden = batches.length === 0;

    // Kartu yang tidak lagi dilaporkan server sudah tidak perlu ditampilkan.
    list.querySelectorAll("[data-batch]").forEach((item) => {
        if (!shown.has(item.dataset.batch)) {
            item.remove();
        }
    });

    batches.forEach((batch) => {
        let item = list.querySelector(`[data-batch="${batch.id}"]`);

        if (item === null) {
            item = document.createElement("article");
            list.append(item);
            buildBatch(item, batch, template.replace("__BATCH__", batch.id));
        } else {
            item.dataset.tone = batchTone(batch);

            const percent = item.querySelector("[data-batch-percent]");

            if (percent) {
                percent.textContent = `${batch.percent}%`;
            }

            const note = item.querySelector("[data-batch-note]");

            if (note) {
                note.textContent = batch.note;
            }

            const fill = item.querySelector("[data-batch-bar]");

            if (fill) {
                fill.style.width = `${batch.percent}%`;
            }

            batch.rows.forEach((row) => {
                const line = item.querySelector(`[data-row="${row.id}"]`);

                if (line === null) {
                    return;
                }

                line.dataset.tone = row.status;

                const rowNote = line.querySelector(".queue-note");

                if (rowNote) {
                    rowNote.textContent = row.note;
                }
            });

            updateBatchAlert(item, batch);
            updateBatchRetry(
                item,
                batch,
                template.replace("__BATCH__", batch.id),
            );
        }

        scheduleBatchRemoval(root, list, item, batch);
    });
}

function setupFaceProgress() {
    const root = document.querySelector("[data-face-progress]");

    if (!root) {
        return;
    }

    const endpoint = root.dataset.endpoint;
    const list = root.querySelector("[data-face-batches]");

    if (!endpoint || !list) {
        return;
    }

    let busy = false;
    let settled = true;

    async function tick() {
        if (busy) {
            return;
        }

        busy = true;

        try {
            const response = await fetch(`${endpoint}?saat=${Date.now()}`, {
                headers: { "X-Requested-With": "XMLHttpRequest" },
                credentials: "same-origin",
            });

            if (response.ok) {
                const data = await response.json();

                applyFaceProgress(root, list, data);

                if (data.unfinished === true) {
                    settled = false;
                } else if (!settled) {
                    settled = true;
                    window.setTimeout(refreshLiveRegions, 400);
                }
            }
        } catch (error) {
            // Sambungan terputus: percobaan berikutnya menyusul.
        }

        busy = false;
    }

    faceProgressTick = tick;
    window.setInterval(tick, 3000);
    tick();
}

/* ---------- Mulai ---------- */

function boot() {
    setupDrawer();
    setupLiveRegions();
    setupPrintButtons();
    setupConfirms();
    setupAutoSubmit();
    setupCameras();
    setupBulkUpload();
    setupFaceProgress();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
} else {
    boot();
}
