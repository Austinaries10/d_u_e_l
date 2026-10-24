// Rewarded ads: show one video ad and say whether the player watched it to the end.
// showRewarded(cfg) -> Promise<"done" | "skipped" | "none">; cfg = the GM's ads settings { provider, id }.
// Each network's script is loaded the first time it's needed.
const loaded = {};
function loadScript(src, before) {
  return loaded[src] ??= new Promise((ok, fail) => { before?.(); const s = document.createElement("script"); s.src = src; s.async = true; s.onload = ok; s.onerror = () => { delete loaded[src]; fail(new Error("blocked")); }; document.head.append(s); });
}
const timeout = (p, ms) => Promise.race([p, new Promise(r => setTimeout(() => r("none"), ms))]);

// AdinPlay (api.adinplay.com): publisher ID, and the site the tag was made for
async function adinplay(id) {
  window.aiptag = window.aiptag || { cmd: [] }; aiptag.cmd.player = aiptag.cmd.player || []; aiptag.cmd.display = aiptag.cmd.display || [];
  await loadScript(`https://api.adinplay.com/libs/aiptag/pub/${id}/${location.hostname}/tag.min.js`);
  return new Promise(res => {
    let granted = false;
    aiptag.cmd.player.push(() => {
      aiptag.adplayer = new aipPlayer({
        AIP_REWARDEDGRANTED: () => { granted = true; },
        AIP_REWARDEDCOMPLETE: state => res(granted ? "done" : state === "empty" || state === "timeout" || state === "unsupported" ? "none" : "skipped"),
      });
      aiptag.adplayer.startRewardedAd({ preload: false, showLoading: true });
    });
  });
}
// GameDistribution (html5.api.gamedistribution.com): game ID
let gdEvents = null;
async function gamedistribution(id) {
  await loadScript("https://html5.api.gamedistribution.com/main.min.js", () => {
    window.GD_OPTIONS = { gameId: id, onEvent: e => gdEvents?.(e) };
  });
  return new Promise(res => {
    let granted = false;
    gdEvents = e => { if (e.name === "SDK_REWARDED_WATCH_COMPLETE") granted = true; };
    window.gdsdk.showAd("rewarded").then(() => res(granted ? "done" : "skipped")).catch(() => res("none"));
  });
}
// Google H5 Games Ads (AdSense): ca-pub-… client ID
async function h5(id) {
  await loadScript(`https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=${id}`, () => {
    window.adsbygoogle = window.adsbygoogle || []; window.adBreak = window.adConfig = function (o) { adsbygoogle.push(o); };
    adConfig({ preloadAdBreaks: "on", sound: "on" });
  });
  return new Promise(res => {
    let granted = false, ready = false;
    adBreak({ type: "reward", name: "coins",
      beforeReward: show => { ready = true; show(); },
      adViewed: () => { granted = true; }, adDismissed: () => {},
      adBreakDone: () => res(granted ? "done" : ready ? "skipped" : "none") });
  });
}
// a pretend ad for trying the whole thing out: 5 seconds with a countdown, can be closed early
function test() {
  return new Promise(res => {
    const box = document.createElement("div");
    box.style.cssText = "position:fixed;inset:0;z-index:99;background:#000d;display:grid;place-items:center;color:#fff;font:600 18px Sora,sans-serif;text-align:center";
    box.innerHTML = `<div><div style="font-size:13px;opacity:.7;letter-spacing:.2em">TEST AD</div><div style="font-size:64px;margin:10px" id="adT">5</div><button type="button" style="padding:8px 14px;border-radius:10px;border:1px solid #fff6;background:none;color:#fff">Close</button></div>`;
    document.body.append(box);
    let n = 5; const t = setInterval(() => { n--; box.querySelector("#adT").textContent = n || "✓"; if (!n) { clearInterval(t); setTimeout(() => { box.remove(); res("done"); }, 500); } }, 1000);
    box.querySelector("button").onclick = () => { clearInterval(t); box.remove(); res(n ? "skipped" : "done"); };
  });
}

export async function showRewarded(cfg) {
  try {
    if (cfg.provider === "test") return await test();
    if (!cfg.id) return "none";
    const run = { adinplay, gamedistribution, h5 }[cfg.provider];
    return run ? await timeout(run(cfg.id), 60000) : "none";
  } catch { return "none"; } // blocked by an ad blocker, or the network has no ad
}
