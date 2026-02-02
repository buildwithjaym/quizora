// public/assets/js/app.js

(function () {
    function $(id) {
        return document.getElementById(id);
    }

    function createToast(title, message) {
        var root = $("toast-root");
        if (!root) return;

        var toast = document.createElement("div");
        toast.className = "toast";

        var dot = document.createElement("div");
        dot.className = "toast__dot";

        var body = document.createElement("div");

        var h = document.createElement("p");
        h.className = "toast__title";
        h.textContent = title;

        var p = document.createElement("p");
        p.className = "toast__msg";
        p.textContent = message;

        body.appendChild(h);
        body.appendChild(p);

        toast.appendChild(dot);
        toast.appendChild(body);

        root.appendChild(toast);

        window.setTimeout(function () {
            if (toast && toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 3500);
    }

    function readFlashToast() {
        var el = document.querySelector("[data-toast-title]");
        if (!el) return;

        var t = el.getAttribute("data-toast-title");
        var m = el.getAttribute("data-toast-msg");

        if (t && m) {
            createToast(t, m);
        }
    }

    // Quiz timer helpers (used later in take.php)
    function pad2(n) {
        n = parseInt(n, 10);
        if (isNaN(n) || n < 0) n = 0;
        return n < 10 ? "0" + n : "" + n;
    }

    function setText(id, text) {
        var el = $(id);
        if (!el) return;
        el.textContent = text;
    }

    function setWidth(id, pct) {
        var el = $(id);
        if (!el) return;
        el.style.width = pct + "%";
    }

    function startCountdown(opts) {
        // opts: { secondsTotal, onTick, onExpire }
        var total = parseInt(opts.secondsTotal, 10);
        if (isNaN(total) || total <= 0) total = 1;

        var startMs = Date.now();
        var timer = null;

        function tick() {
            var elapsed = Math.floor((Date.now() - startMs) / 1000);
            if (elapsed < 0) elapsed = 0;

            var left = total - elapsed;
            if (left < 0) left = 0;

            if (typeof opts.onTick === "function") {
                opts.onTick({
                    secondsLeft: left,
                    secondsUsed: elapsed,
                    mmss: pad2(Math.floor(left / 60)) + ":" + pad2(left % 60),
                    pctUsed: Math.min(100, Math.max(0, Math.floor((elapsed / total) * 100)))
                });
            }

            if (left <= 0) {
                stop();
                if (typeof opts.onExpire === "function") {
                    opts.onExpire();
                }
            }
        }

        function stop() {
            if (timer) {
                window.clearInterval(timer);
                timer = null;
            }
        }

        tick();
        timer = window.setInterval(tick, 250);

        return { stop: stop };
    }

    // Expose minimal API
    window.Quizora = window.Quizora || {};
    window.Quizora.toast = createToast;
    window.Quizora.countdown = startCountdown;
    window.Quizora.setText = setText;
    window.Quizora.setWidth = setWidth;

    document.addEventListener("DOMContentLoaded", function () {
        readFlashToast();
    });
})();
