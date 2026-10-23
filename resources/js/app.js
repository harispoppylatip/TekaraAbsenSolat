/* =========================================================
   Absen Solat SMKIM4
   Menangani menu samping, penyegaran wilayah halaman, kamera
   absen, kemajuan pemrosesan wajah, dan tombol cetak.
   ========================================================= */

const LIVE_INTERVAL = 5000;

function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');

    return meta ? meta.getAttribute("content") : "";
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

        const submitter = event.submitter;
        const message = submitter?.dataset?.confirm || form.dataset.confirm;

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
    const cctvGroup = picker.querySelector("[data-cctv-group]");
    const clonedCctv = cctvGroup ? cctvGroup.cloneNode(true) : null;

    picker.innerHTML = "";

    const facingGroup = document.createElement("optgroup");
    facingGroup.label = "Arah kamera peramban";

    CAMERA_FACING_OPTIONS.forEach(([value, label]) => {
        const option = document.createElement("option");
        option.value = value;
        option.textContent = label;
        facingGroup.appendChild(option);
    });

    picker.appendChild(facingGroup);

    if (clonedCctv) {
        picker.appendChild(clonedCctv);
    }

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

/* ---------- Kotak dan nama wajah di atas gambar kamera ---------- */

// Warna kotak mengikuti keadaan wajah, bukan sekadar hiasan: hijau berarti
// kehadirannya tercatat, kuning berarti belum bisa diputuskan, dan abu-abu
// berarti masih diperiksa. Yang menentukan boleh tidaknya dicatat tetap
// jawaban server, bukan warnanya.
const FACE_TONES = {
    recorded: "saved",
    late: "saved",
    duplicate: "saved",
    unknown: "unknown",
    face_too_small: "unknown",
    ambiguous: "unknown",
    no_face: "unknown",
    invalid: "unknown",
    unavailable: "unknown",
};

function faceTone(face, busy) {
    const status = String(face.status || "");

    if (status === "recorded" || status === "late" || status === "duplicate") {
        return FACE_TONES[status];
    }

    // Selagi permintaan masih berjalan, wajah yang belum tercatat ditandai
    // sedang diperiksa supaya tidak dikira sudah selesai.
    return busy ? "checking" : FACE_TONES[status] || "unknown";
}

function faceLabel(face) {
    if (typeof face.label === "string" && face.label !== "") {
        return face.label;
    }

    if (face.member && face.member.name) {
        return face.member.name;
    }

    return "Tidak dikenal";
}

function faceStatusLine(face, busy) {
    const status = String(face.status || "");

    if (
        busy &&
        status !== "recorded" &&
        status !== "late" &&
        status !== "duplicate"
    ) {
        return "Memverifikasi...";
    }

    if (
        face.attendance &&
        typeof face.attendance.display_message === "string"
    ) {
        return face.attendance.display_message;
    }

    return "Belum tercatat";
}

function toneColor(tone) {
    const styles = getComputedStyle(document.documentElement);
    const pick = (name, fallback) => {
        const value = styles.getPropertyValue(name).trim();

        return value === "" ? fallback : value;
    };

    if (tone === "saved") {
        return pick("--success", "#1b6b3a");
    }

    if (tone === "unknown") {
        return pick("--warning", "#8a5300");
    }

    return pick("--on-surface-variant", "#5c6470");
}

/**
 * Perbandingan koordinat gambar asli dengan gambar yang benar benar terlihat.
 *
 * Video ditampilkan dengan `object-fit: cover`, jadi gambarnya terpotong di
 * sisi yang lebih panjang. Tanpa perhitungan ini kotak wajah akan bergeser
 * setiap kali bentuk layar berubah, sedangkan kotak yang meleset lebih buruk
 * daripada tidak ada kotak sama sekali.
 */
function coverMapping(video, width, height, frame) {
    const sumberLebar =
        frame && frame.width > 0 ? frame.width : video.videoWidth || 1;
    const sumberTinggi =
        frame && frame.height > 0 ? frame.height : video.videoHeight || 1;
    const isiLebar = video.videoWidth || sumberLebar;
    const isiTinggi = video.videoHeight || sumberTinggi;

    const skala = Math.max(width / isiLebar, height / isiTinggi);

    return {
        geserX: (width - isiLebar * skala) / 2,
        geserY: (height - isiTinggi * skala) / 2,
        skalaX: (isiLebar / sumberLebar) * skala,
        skalaY: (isiTinggi / sumberTinggi) * skala,
    };
}

/**
 * Apakah gambar kamera ditampilkan tercermin.
 *
 * Beberapa peramban ponsel mencerminkan gambar kamera depan. Bila itu terjadi,
 * posisi kotaknya juga harus dicerminkan supaya tetap menempel pada wajahnya.
 */
function cameraMirrored(video) {
    const transform = getComputedStyle(video).transform;

    if (typeof transform !== "string" || transform === "none") {
        return false;
    }

    const bagian = transform
        .replace(/^matrix/, "")
        .replace(/[()]/g, "")
        .split(",");

    return bagian.length >= 1 && Number(bagian[0]) < 0;
}

function drawFaceOverlay(root, faces, frame, busy) {
    const overlay = root.querySelector("[data-camera-overlay]");
    const video = root.querySelector("[data-camera-video]");

    if (!overlay || !video) {
        return;
    }

    const kotak = overlay.getBoundingClientRect();
    const rasio = window.devicePixelRatio || 1;

    if (kotak.width === 0 || kotak.height === 0) {
        return;
    }

    overlay.width = Math.round(kotak.width * rasio);
    overlay.height = Math.round(kotak.height * rasio);

    const gambar = overlay.getContext("2d");

    gambar.setTransform(rasio, 0, 0, rasio, 0, 0);
    gambar.clearRect(0, 0, kotak.width, kotak.height);

    if (!faces || faces.length === 0) {
        return;
    }

    const petakan = coverMapping(video, kotak.width, kotak.height, frame);
    const cermin = cameraMirrored(video);
    const sumberLebar =
        frame && frame.width > 0 ? frame.width : video.videoWidth || 1;

    faces.forEach((face) => {
        const box = face.box || {};

        // Pencerminan dikerjakan pada koordinat gambar asli dulu, baru
        // diskalakan. Mencerminkannya setelah diskalakan membuat kotaknya
        // meleset jauh karena satuannya sudah berubah.
        let kiri = Number(box.left || 0);
        let kanan = Number(box.right || 0);

        if (cermin) {
            const kiriAsli = kiri;

            kiri = sumberLebar - kanan;
            kanan = sumberLebar - kiriAsli;
        }

        const x = petakan.geserX + kiri * petakan.skalaX;
        const y = petakan.geserY + Number(box.top || 0) * petakan.skalaY;
        const lebar = Math.max(2, (kanan - kiri) * petakan.skalaX);
        const tinggi = Math.max(
            2,
            (Number(box.bottom || 0) - Number(box.top || 0)) * petakan.skalaY,
        );

        const tone = faceTone(face, busy);
        const warna = toneColor(tone);

        gambar.lineWidth = 2.5;
        gambar.strokeStyle = warna;
        gambar.strokeRect(x, y, lebar, tinggi);

        const nama = faceLabel(face);
        const keadaan = faceStatusLine(face, busy);

        gambar.font = "600 14px Inter, system-ui, sans-serif";

        const lebarNama = gambar.measureText(nama).width;

        gambar.font = "500 12px Inter, system-ui, sans-serif";

        const lebarKeadaan = gambar.measureText(keadaan).width;
        const lebarLabel = Math.min(
            kotak.width,
            Math.max(lebarNama, lebarKeadaan) + 16,
        );
        const tinggiLabel = 34;

        // Label dijepit ke dalam bingkai supaya wajah di tepi gambar tidak
        // membuat namanya terpotong.
        const xLabel = Math.max(0, Math.min(kotak.width - lebarLabel, x));
        const yLabel = y - tinggiLabel < 0 ? y : y - tinggiLabel;

        gambar.fillStyle = warna;
        gambar.fillRect(xLabel, yLabel, lebarLabel, tinggiLabel);

        gambar.fillStyle = "#ffffff";
        gambar.font = "600 14px Inter, system-ui, sans-serif";
        gambar.fillText(nama, xLabel + 8, yLabel + 15, lebarLabel - 16);

        gambar.font = "500 12px Inter, system-ui, sans-serif";
        gambar.fillText(keadaan, xLabel + 8, yLabel + 29, lebarLabel - 16);
    });
}

function initCamera(root) {
    const video = root.querySelector("[data-camera-video]");
    const cctvImg = root.querySelector("[data-camera-cctv-img]");
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
    let isCctvMode = false;
    let cctvUseSnapshot = false;
    let cctvStreamUrl = "";
    let cctvSnapshotUrl = "";

    // Wajah dari balasan terakhir, beserta ukuran gambar yang dipakai server.
    // Dipakai menggambar ulang kotaknya saat ukuran tampilan berubah.
    let faces = [];
    let frame = null;

    // Nomor urut percobaan menyalakan kamera. Izin kamera bisa lama diminta
    // peramban, jadi hasil percobaan lama tidak boleh lagi mengubah tombol,
    // teks status, maupun pencatatan bila kamera sudah dimatikan atau diganti.
    let attempt = 0;

    function say(text) {
        if (status) {
            status.textContent = text;
        }
    }

    function addLine(message, ok, photoUrl, memberKey = null, isTest = false) {
        if (!report) {
            return;
        }

        const time = new Date().toLocaleTimeString("id-ID", {
            hour: "2-digit",
            minute: "2-digit",
            second: "2-digit",
        });

        // Di mode uji coba, jika orang yang sama sudah tercatat di laporan kamera,
        // timpa kartu lama alih-alih membuat kartu baru.
        let line = null;
        if (isTest && memberKey) {
            line = report.querySelector(`[data-member-key="${memberKey}"]`);
        }

        const isNew = !line;
        if (isNew) {
            line = document.createElement("p");
            if (memberKey) {
                line.dataset.memberKey = String(memberKey);
            }
        } else {
            line.innerHTML = "";
        }

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
        if (stream !== null || isCctvMode) {
            return;
        }

        if (picker && picker.value) {
            facing = picker.value;
        }

        const isCctv = typeof facing === "string" && facing.startsWith("cctv:");

        if (isCctv) {
            const selectedOpt = picker ? picker.selectedOptions[0] : null;
            if (!selectedOpt) {
                return;
            }

            const camType = selectedOpt.dataset.camType || "web_stream";
            cctvStreamUrl = selectedOpt.dataset.camStream || "";
            cctvSnapshotUrl = selectedOpt.dataset.camSnapshot || "";
            isCctvMode = true;

            if (startButton) {
                startButton.disabled = true;
            }

            if (stopButton) {
                stopButton.disabled = false;
            }

            if (placeholder) {
                placeholder.hidden = true;
            }

            if (camType === "web_stream") {
                say("Menghubungkan ke siaran Web Stream...");
                video.style.transform = "none";
                video.crossOrigin = "anonymous";
                video.src = cctvStreamUrl;
                video.hidden = false;

                video.play().then(() => {
                    say("Siaran Web Stream aktif. Memindai wajah...");
                }).catch(() => {
                    cctvUseSnapshot = true;
                    video.hidden = true;
                    if (cctvImg) {
                        cctvImg.style.display = "block";
                    }
                    say("Siaran Web Stream aktif (mode cuplikan). Memindai wajah...");
                });
            } else {
                cctvUseSnapshot = true;
                video.hidden = true;
                if (cctvImg) {
                    cctvImg.style.display = "block";
                }
                say("Kamera RTSP CCTV aktif. Memindai wajah...");
            }

            timer = window.setInterval(capture, interval);
            capture();
            return;
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

        // Cek apakah kamera yang aktif adalah kamera depan
        const isUserFacing =
            typeof facing === "string" &&
            (facing === "facing:user" ||
                facing.includes("user") ||
                facing.toLowerCase().includes("front"));
        video.style.transform = isUserFacing ? "scaleX(-1)" : "none";
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
            video.pause();
            video.srcObject = null;
            video.src = "";
            video.removeAttribute("src");
            video.hidden = true;
        }

        if (cctvImg) {
            cctvImg.style.display = "none";
            cctvImg.removeAttribute("src");
        }

        isCctvMode = false;
        cctvUseSnapshot = false;
        cctvStreamUrl = "";
        cctvSnapshotUrl = "";

        if (placeholder) {
            placeholder.hidden = false;
        }

        // Kotak dan nama ikut dihapus saat kamera dimatikan. Kalau tidak, nama
        // orang terakhir tertinggal di layar seolah masih ada yang terdeteksi.
        faces = [];
        frame = null;
        drawFaceOverlay(root, faces, frame, false);
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
        if (busy || (stream === null && !isCctvMode) || !canvas) {
            return;
        }

        busy = true;

        // Wajah yang belum tercatat ditandai sedang diperiksa selagi permintaan
        // berjalan, supaya petugas tahu pemeriksaannya masih berjalan.
        drawFaceOverlay(root, faces, frame, true);

        try {
            let blob = null;
            let activeWidth = 480;
            let activeHeight = 360;

            if (isCctvMode && cctvUseSnapshot) {
                const snapUrl =
                    cctvSnapshotUrl +
                    (cctvSnapshotUrl.includes("?") ? "&" : "?") +
                    "_t=" +
                    Date.now();
                const snapRes = await fetch(snapUrl, {
                    credentials: "same-origin",
                }).catch(() => null);

                if (snapRes && snapRes.ok) {
                    blob = await snapRes.blob();
                    if (blob) {
                        const objectUrl = URL.createObjectURL(blob);
                        if (cctvImg) {
                            cctvImg.src = objectUrl;
                        }

                        await new Promise((resolve) => {
                            const img = new Image();
                            img.onload = () => {
                                activeWidth = img.naturalWidth || 640;
                                activeHeight = img.naturalHeight || 480;
                                canvas.width = activeWidth;
                                canvas.height = activeHeight;
                                canvas
                                    .getContext("2d")
                                    .drawImage(img, 0, 0, activeWidth, activeHeight);
                                resolve();
                            };
                            img.onerror = resolve;
                            img.src = objectUrl;
                        });
                    }
                }
            } else {
                activeWidth = video.videoWidth || 480;
                activeHeight = video.videoHeight || 360;

                canvas.width = activeWidth;
                canvas.height = activeHeight;
                canvas.getContext("2d").drawImage(video, 0, 0, activeWidth, activeHeight);

                blob = await new Promise((resolve) =>
                    canvas.toBlob(resolve, "image/jpeg", 0.85),
                );
            }

            if (!blob) {
                busy = false;
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
                busy = false;
                return;
            }

            // Kotak wajah digambar dari jawaban server setiap putaran, jadi
            // posisinya selalu berasal dari deteksi nyata, bukan perkiraan.
            // Bila tidak ada wajah lagi, daftarnya kosong dan kotak lama hilang.
            faces = Array.isArray(data.faces) ? data.faces : [];
            frame = {
                width: Number(data.width || activeWidth),
                height: Number(data.height || activeHeight),
            };

            drawFaceOverlay(root, faces, frame, false);

            const berhasil = faces.filter(
                (face) => face.attendance && face.attendance.recorded,
            );

            const isTest = Boolean(data.session && data.session.is_test);

            // Setiap orang dilaporkan pada barisnya sendiri supaya kehadiran
            // satu orang tidak menutupi kabar orang lain. Pesannya diambil dari
            // server, jadi keterangan seperti "data ini tidak dihitung pada
            // rekap" pada mode uji coba ikut terbaca.
            if (berhasil.length > 0) {
                berhasil.forEach((face) => {
                    const catatan =
                        (face.attendance && face.attendance.message) || "";
                    const pesan =
                        catatan !== ""
                            ? catatan
                            : `${faceLabel(face)} tercatat.`;
                    const memberKey =
                        face.member && face.member.id
                            ? `user-${face.member.id}`
                            : (face.log_id ? `log-${face.log_id}` : `label-${faceLabel(face)}`);

                    addLine(pesan, true, data.proof_url || "", memberKey, isTest);
                });

                lastMessage = "";
                refreshLiveRegions();

                return;
            }

            const message =
                data.message || data.display_message || "Tidak ada keterangan.";

            if (message === lastMessage) {
                return;
            }

            if (faces.length === 0) {
                lastMessage = "";

                return;
            }

            // Rincian penolakan per wajah, masing masing satu baris, supaya
            // petugas tahu siapa yang bermasalah dan kenapa.
            const rincian = faces
                .map((face) => {
                    const catatan =
                        (face.attendance && face.attendance.message) || "";

                    return catatan !== ""
                        ? catatan
                        : `${faceLabel(face)}: ${faceStatusLine(face, false)}`;
                })
                .join(" ");

            lastMessage = message;
            addLine(rincian, false);
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

    // Ukuran tampilan berubah, misalnya karena layar diputar atau jendela
    // diubah ukurannya. Kotaknya digambar ulang dengan ukuran yang baru.
    window.addEventListener("resize", () => {
        drawFaceOverlay(root, faces, frame, busy);
    });

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

    // Ditaruh tepat di bawah kepala kartu, sama seperti versi Blade, supaya
    // peringatan ini tidak tertimbun daftar foto yang panjang.
    const head = item.querySelector(".queue-head");

    if (head) {
        head.after(line);
    } else {
        item.prepend(line);
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
    setupFaceProgress();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
} else {
    boot();
}
