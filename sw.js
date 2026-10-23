// Service worker: lets AR Duel be installed on the home screen and open quickly.
// The page and all game data come from the network first (accounts, saves and duels need it); card pictures,
// character faces and the rules engine files never change at their address, so they are kept on the phone.
const CACHE = "arduel-1";

self.addEventListener("install", e => {
  self.skipWaiting();
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(["./", "manifest.json", "icons/icon-192.png"])).catch(() => {}));
});
self.addEventListener("activate", e => {
  e.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});

// files that are the same forever at their address
function keep(u) {
  if (u.pathname.includes("/engine/ocgcore/") || u.pathname.includes("/icons/")) return true;
  if (/\/(engine\/worker\.js|data\/recipes\.txt|arena\.js)$/.test(u.pathname) && u.searchParams.has("v")) return true; // a new release changes ?v=
  return /\/api\/ygo\.php$/.test(u.pathname) && ["img", "face"].includes(u.searchParams.get("action"));
}

self.addEventListener("fetch", e => {
  const r = e.request, u = new URL(r.url);
  if (r.method !== "GET" || u.origin !== location.origin) return;
  if (keep(u)) {
    e.respondWith(caches.open(CACHE).then(async c => (await c.match(r)) || fetch(r).then(res => { if (res.ok) c.put(r, res.clone()); return res; })));
  } else if (r.mode === "navigate") {
    // always the newest page; the saved one only when there's no connection
    e.respondWith(fetch(r).then(res => { if (res.ok) caches.open(CACHE).then(c => c.put("./", res.clone())); return res; }).catch(() => caches.match("./")));
  }
});
