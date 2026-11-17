import Echo from "laravel-echo";
import Pusher from "pusher-js";

const subscribers = new Set();
let echoInstance = null;
let initialized = false;
let connected = false;

function initRealtime() {
    if (initialized) {
        return;
    }
    initialized = true;

    const cfg = window.__REALTIME_CONFIG__ || {};
    if (cfg.mode !== "websocket" || !cfg.key) {
        return;
    }

    window.Pusher = Pusher;

    const isHttps = window.location.protocol === "https:";
    const defaultPort = isHttps ? 443 : (Number(window.location.port) || 80);

    try {
        echoInstance = new Echo({
            broadcaster: "reverb",
            key: cfg.key,
            wsHost: window.location.hostname,
            wsPort: defaultPort,
            wssPort: defaultPort,
            forceTLS: isHttps,
            enabledTransports: ["ws", "wss"],
            disableStats: true,
        });

        const pusher = echoInstance.connector?.pusher;
        if (pusher && pusher.connection) {
            pusher.connection.bind("connected", () => {
                connected = true;
            });
            pusher.connection.bind("disconnected", () => {
                connected = false;
            });
            pusher.connection.bind("error", () => {
                connected = false;
            });
        }

        const notify = (type, payload) => {
            subscribers.forEach((fn) => {
                try {
                    fn({ type, ...(payload || {}) });
                } catch (e) {
                    // Abaikan error handler tunggal
                }
            });
        };

        echoInstance
            .channel("solat.realtime")
            .listen(".attendance.recorded", (payload) => {
                notify("attendance", payload);
            })
            .listen(".session.updated", (payload) => {
                notify("session", payload);
            });
    } catch (e) {
        connected = false;
    }
}

window.SolatRealtime = {
    mode() {
        return (window.__REALTIME_CONFIG__ && window.__REALTIME_CONFIG__.mode) || "polling";
    },
    isConnected() {
        return connected;
    },
    subscribe(callback) {
        if (typeof callback !== "function") {
            return () => {};
        }
        initRealtime();
        subscribers.add(callback);
        return () => subscribers.delete(callback);
    },
};

initRealtime();
