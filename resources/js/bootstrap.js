import axios from "axios";
import Echo from "laravel-echo";
import Pusher from "pusher-js";

window.axios = axios;
window.axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";

window.Pusher = Pusher;

const getBearerToken = () => localStorage.getItem("auth_token");

window.Echo = new Echo({
    broadcaster: "reverb",
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST || "192.168.100.184",
    wsPort: import.meta.env.VITE_REVERB_PORT ? Number(import.meta.env.VITE_REVERB_PORT) : 8083,
    wssPort: import.meta.env.VITE_REVERB_PORT ? Number(import.meta.env.VITE_REVERB_PORT) : 8083,
    forceTLS: false,
    enabledTransports: ["ws", "wss"],

    authEndpoint: "/api/broadcasting/auth",
    auth: {
        headers: {
            Authorization: getBearerToken() ? `Bearer ${getBearerToken()}` : "",
            Accept: "application/json",
        },
    },
});