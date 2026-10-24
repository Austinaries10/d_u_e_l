// Music and sound effects, all made in the browser with the Web Audio API (no sound files to download).
// sound.play(name) for an effect, sound.music(scene) for the background music: "menu", "duel", "pack" or null.
// Browsers only allow sound after the first tap, so nothing plays until sound.unlock() is called from one.
let ctx = null, master, musicBus, sfxBus, noiseBuf = null, verb = null;
const store = (k, d) => { try { const v = localStorage.getItem("arduel." + k); return v == null ? d : +v; } catch { return d; } };
const vol = { music: store("music2", 0.6), sfx: store("sfx2", 0.9) };

function init() {
  if (ctx) return true;
  const AC = window.AudioContext || window.webkitAudioContext; if (!AC) return false;
  ctx = new AC();
  const comp = ctx.createDynamicsCompressor(); comp.threshold.value = -10; comp.ratio.value = 6; comp.attack.value = 0.003; comp.release.value = 0.2; comp.connect(ctx.destination);
  master = ctx.createGain(); master.gain.value = 2.2; master.connect(comp);
  musicBus = ctx.createGain(); musicBus.gain.value = vol.music; musicBus.connect(master);
  sfxBus = ctx.createGain(); sfxBus.gain.value = vol.sfx; sfxBus.connect(master);
  noiseBuf = ctx.createBuffer(1, ctx.sampleRate * 2, ctx.sampleRate);
  const d = noiseBuf.getChannelData(0); for (let i = 0; i < d.length; i++) d[i] = Math.random() * 2 - 1;
  // a soft echo, for space
  verb = ctx.createDelay(1); verb.delayTime.value = 0.23; const fb = ctx.createGain(); fb.gain.value = 0.32; const vf = ctx.createBiquadFilter(); vf.type = "lowpass"; vf.frequency.value = 2400;
  verb.connect(vf); vf.connect(fb); fb.connect(verb); const vout = ctx.createGain(); vout.gain.value = 0.35; vf.connect(vout); vout.connect(master);
  return true;
}
const now = () => ctx.currentTime;
const mtof = m => 440 * 2 ** ((m - 69) / 12);
// one note: an oscillator through an envelope (and optionally a filter), into a bus
function tone(bus, { f = 440, f2 = null, type = "sine", t = now(), a = 0.005, d = 0.2, g = 0.3, lp = 0, q = 0.7, detune = 0, echo = 0, vib = 0, bend = 0 }) {
  const o = ctx.createOscillator(), e = ctx.createGain(); o.type = type; o.frequency.setValueAtTime(f, t); o.detune.setValueAtTime(detune + bend, t);
  if (bend) o.detune.linearRampToValueAtTime(detune, t + 0.06); // a plucked string settles onto its note
  if (vib) { const l = ctx.createOscillator(), lg = ctx.createGain(); l.frequency.value = 5.2; lg.gain.setValueAtTime(0, t); lg.gain.linearRampToValueAtTime(vib, t + a + 0.15); l.connect(lg); lg.connect(o.detune); l.start(t); l.stop(t + a + d + 0.05); }
  if (f2) o.frequency.exponentialRampToValueAtTime(Math.max(20, f2), t + a + d);
  e.gain.setValueAtTime(0.0001, t); e.gain.exponentialRampToValueAtTime(g, t + a); e.gain.exponentialRampToValueAtTime(0.0001, t + a + d);
  let n = o; if (lp) { const fl = ctx.createBiquadFilter(); fl.type = "lowpass"; fl.frequency.value = lp; fl.Q.value = q; o.connect(fl); n = fl; }
  n.connect(e); e.connect(bus); if (echo) { const s = ctx.createGain(); s.gain.value = echo; e.connect(s); s.connect(verb); }
  o.start(t); o.stop(t + a + d + 0.05);
}
// a burst of noise through a filter whose frequency can sweep
function noise(bus, { t = now(), a = 0.005, d = 0.2, g = 0.3, type = "bandpass", f = 1000, f2 = null, q = 1, echo = 0 }) {
  const s = ctx.createBufferSource(), fl = ctx.createBiquadFilter(), e = ctx.createGain(); s.buffer = noiseBuf;
  fl.type = type; fl.frequency.setValueAtTime(f, t); fl.Q.value = q; if (f2) fl.frequency.exponentialRampToValueAtTime(f2, t + a + d);
  e.gain.setValueAtTime(0.0001, t); e.gain.exponentialRampToValueAtTime(g, t + a); e.gain.exponentialRampToValueAtTime(0.0001, t + a + d);
  s.connect(fl); fl.connect(e); e.connect(bus); if (echo) { const x = ctx.createGain(); x.gain.value = echo; e.connect(x); x.connect(verb); }
  s.start(t, Math.random()); s.stop(t + a + d + 0.05);
}

// ---------- Sound effects ----------
const FX = {
  click: () => tone(sfxBus, { f: 1250, type: "triangle", d: 0.05, g: 0.12 }),
  draw: () => noise(sfxBus, { f: 2500, f2: 6000, d: 0.13, g: 0.22, q: 0.8 }),
  set: () => { tone(sfxBus, { f: 160, f2: 70, d: 0.12, g: 0.35 }); noise(sfxBus, { f: 3000, d: 0.04, g: 0.1 }); },
  flip: () => { noise(sfxBus, { f: 1800, f2: 4500, d: 0.09, g: 0.2 }); tone(sfxBus, { f: 660, type: "triangle", t: now() + 0.05, d: 0.08, g: 0.1 }); },
  summon: () => { tone(sfxBus, { f: 220, f2: 880, type: "sawtooth", a: 0.02, d: 0.35, g: 0.12, lp: 2200, echo: 0.4 }); noise(sfxBus, { f: 800, f2: 5000, a: 0.05, d: 0.35, g: 0.14, echo: 0.3 }); [76, 83, 88].forEach((m, i) => tone(sfxBus, { f: mtof(m), t: now() + 0.18 + i * 0.05, d: 0.4, g: 0.07, echo: 0.6 })); },
  bigSummon: () => {
    tone(sfxBus, { f: 70, f2: 35, a: 0.01, d: 1.1, g: 0.6 }); noise(sfxBus, { type: "lowpass", f: 400, d: 0.9, g: 0.35 });
    noise(sfxBus, { f: 300, f2: 6000, a: 0.6, d: 0.5, g: 0.2, echo: 0.5 });
    [57, 64, 69, 73, 76].forEach((m, i) => tone(sfxBus, { f: mtof(m), type: "sawtooth", t: now() + 0.5 + i * 0.07, a: 0.05, d: 1.2, g: 0.06, lp: 2600, echo: 0.6 }));
  },
  activate: () => { [86, 87, 90].forEach((m, i) => tone(sfxBus, { f: mtof(m), t: now() + i * 0.06, d: 0.45, g: 0.09, echo: 0.6 })); noise(sfxBus, { f: 6000, d: 0.3, g: 0.05, echo: 0.4 }); },
  trap: () => { tone(sfxBus, { f: 110, type: "square", d: 0.25, g: 0.12, lp: 900 }); [83, 82, 81].forEach((m, i) => tone(sfxBus, { f: mtof(m), type: "square", t: now() + i * 0.08, d: 0.12, g: 0.06, lp: 2500 })); },
  attack: () => { noise(sfxBus, { type: "highpass", f: 6000, f2: 900, a: 0.02, d: 0.28, g: 0.35 }); tone(sfxBus, { f: 900, f2: 300, type: "sawtooth", a: 0.01, d: 0.2, g: 0.06, lp: 3000 }); },
  hit: () => { tone(sfxBus, { f: 180, f2: 60, d: 0.18, g: 0.5 }); noise(sfxBus, { f: 1500, d: 0.12, g: 0.3 }); },
  explode: () => { tone(sfxBus, { f: 90, f2: 30, a: 0.005, d: 0.9, g: 0.7 }); noise(sfxBus, { type: "lowpass", f: 3000, f2: 200, a: 0.005, d: 1.1, g: 0.6, echo: 0.3 }); noise(sfxBus, { f: 5000, d: 0.25, g: 0.25 }); },
  banish: () => { noise(sfxBus, { f: 400, f2: 8000, a: 0.5, d: 0.3, g: 0.22, echo: 0.6 }); [96, 91, 100].forEach((m, i) => tone(sfxBus, { f: mtof(m), t: now() + 0.3 + i * 0.07, d: 0.5, g: 0.05, echo: 0.7 })); },
  damage: () => { tone(sfxBus, { f: 140, f2: 50, type: "square", d: 0.3, g: 0.25, lp: 800 }); noise(sfxBus, { f: 700, d: 0.25, g: 0.3 }); },
  heal: () => [72, 76, 79, 84].forEach((m, i) => tone(sfxBus, { f: mtof(m), t: now() + i * 0.07, d: 0.35, g: 0.08, echo: 0.5 })),
  turn: () => { tone(sfxBus, { f: 196, d: 1.2, g: 0.22, echo: 0.5 }); tone(sfxBus, { f: 392, d: 0.9, g: 0.08, echo: 0.5 }); },
  coin: () => { tone(sfxBus, { f: 2093, type: "triangle", d: 0.15, g: 0.12 }); tone(sfxBus, { f: 2637, type: "triangle", t: now() + 0.08, d: 0.3, g: 0.12, echo: 0.4 }); },
  burst: () => { noise(sfxBus, { f: 200, f2: 7000, a: 0.25, d: 0.4, g: 0.3, echo: 0.5 }); tone(sfxBus, { f: 60, f2: 40, t: now() + 0.25, d: 0.6, g: 0.5 }); },
  rare: () => [84, 88, 91, 96, 100, 103].forEach((m, i) => tone(sfxBus, { f: mtof(m), type: "triangle", t: now() + i * 0.05, d: 0.6, g: 0.07, echo: 0.7 })),
  win: () => [[62, 0], [63, 0.15], [66, 0.3], [67, 0.45], [69, 0.6], [74, 0.9]].forEach(([m, t]) => { tone(sfxBus, { f: mtof(m), type: "sawtooth", t: now() + t, a: 0.01, d: t > 0.8 ? 1.2 : 0.25, g: 0.1, lp: 2500, echo: 0.5 }); tone(sfxBus, { f: mtof(m - 12), type: "triangle", t: now() + t, d: 0.3, g: 0.1 }); }),
  lose: () => [[67, 0], [63, 0.35], [60, 0.7], [55, 1.05]].forEach(([m, t]) => tone(sfxBus, { f: mtof(m), type: "sawtooth", t: now() + t, a: 0.02, d: t > 1 ? 1.4 : 0.4, g: 0.08, lp: 1400, echo: 0.5 })),
};

// ---------- Music: old Egypt ----------
// Everything is in the Hijaz mode (the "Egyptian" scale with its step-and-a-half): a drone on the tonic, a plucked
// oud playing a melody, a breathy ney flute and darbuka drums (doum-tek). The melodies are made from fixed phrases,
// so each song is always the same tune.
const HIJAZ = [0, 1, 4, 5, 7, 8, 10];
const deg = (root, n) => root + 12 * Math.floor(n / 7) + HIJAZ[((n % 7) + 7) % 7]; // scale step n above the root
// voices
const oud = (bus, m, t, d, g = 0.09) => { tone(bus, { f: mtof(m), type: "sawtooth", t, a: 0.004, d, g, lp: 1800, q: 2, bend: 40 }); tone(bus, { f: mtof(m + 12), type: "triangle", t, a: 0.004, d: d * 0.5, g: g * 0.35 }); };
const ney = (bus, m, t, d, g = 0.07) => { tone(bus, { f: mtof(m), type: "sine", t, a: 0.09, d, g, vib: 14, echo: 0.5 }); tone(bus, { f: mtof(m + 12), type: "sine", t, a: 0.1, d, g: g * 0.15 }); noise(bus, { f: mtof(m) * 2, q: 6, t, a: 0.08, d: d * 0.8, g: g * 0.35 }); };
const doum = (bus, t, g = 0.5) => { tone(bus, { f: 105, f2: 62, t, d: 0.3, g }); noise(bus, { type: "lowpass", f: 300, t, d: 0.12, g: g * 0.4 }); };
const tek = (bus, t, g = 0.22) => { noise(bus, { type: "highpass", f: 3200, t, d: 0.06, g }); tone(bus, { f: 1100, type: "triangle", t, d: 0.03, g: g * 0.5 }); };
const ka = (bus, t) => tek(bus, t, 0.09);
const riq = (bus, t) => noise(bus, { type: "highpass", f: 8000, t, d: 0.09, g: 0.06 }); // the jingles of a frame drum
// rhythms over 16 sixteenths: D = doum, T = tek, k = soft tek, . = rest
const RHYTHM = { maqsum: "D..T..T.D..T..k.", saidi: "D..TD.D.D..T..k.", slow: "D.......T...k..." };
// phrases: scale steps, one per sixteenth (null holds the previous note, - is a rest)
const PH = {
  duelA: [0, null, 1, 2, 3, null, 2, 1, 2, null, 1, 0, -1, null, 0, null],
  duelB: [4, null, 3, 4, 5, 4, 3, 2, 3, null, 2, 1, 2, null, 1, 0],
  duelC: [7, null, 6, 5, 4, null, 5, 4, 3, 2, 1, 2, 1, null, 0, null],
  menuA: [0, null, null, null, 1, null, 2, null, 3, null, null, null, 2, null, 1, null],
  menuB: [4, null, null, null, 3, null, 2, null, 1, null, null, null, 0, null, null, null],
  pack: [0, 2, 4, 7, 9, 7, 4, 2, 1, 4, 5, 8, 5, 4, 1, -1],
};
const SONGS = {
  menu: { bpm: 72, root: 50, rhythm: "slow", drone: 0.05, lead: "ney", phrases: ["menuA", "menuA", "menuB", "menuA"], riq: 0 },
  duel: { bpm: 118, root: 50, rhythm: "maqsum", drone: 0.04, lead: "oud", phrases: ["duelA", "duelA", "duelB", "duelC"], riq: 1, ney: ["duelB"], bass: 1, fill: "saidi" },
  pack: { bpm: 92, root: 55, rhythm: "slow", drone: 0.045, lead: "harp", phrases: ["pack", "pack"], riq: 1 },
};
let song = null, songName = null, step = 0, nextT = 0, timer = null, lastNote = 0;
function schedule() {
  if (!song) return;
  const sp = 60 / song.bpm / 4; // a sixteenth
  if (nextT < now() - 0.2) nextT = now() + 0.05; // (back from the background: don't play the missed notes all at once)
  while (nextT < now() + 0.15) {
    const bar = Math.floor(step / 16), s = step % 16, t = nextT, r = song.root, ph = PH[song.phrases[bar % song.phrases.length]];
    // the drone: tonic and fifth, held for two bars
    if (s === 0 && bar % 2 === 0) for (const [m, dt] of [[r - 12, -6], [r - 12, 6], [r - 5, 0]]) tone(musicBus, { f: mtof(m), type: "sawtooth", t, a: 0.8, d: sp * 32, g: song.drone, lp: 600, detune: dt });
    // drums (the last bar of every four plays a busier rhythm)
    const rh = RHYTHM[song.fill && bar % 4 === 3 ? song.fill : song.rhythm][s];
    if (rh === "D") doum(musicBus, t, song.bass ? 0.55 : 0.4); else if (rh === "T") tek(musicBus, t); else if (rh === "k") ka(musicBus, t);
    if (song.riq && s % 4 === 2) riq(musicBus, t);
    if (song.bass && (s === 0 || s === 6 || s === 8)) tone(musicBus, { f: mtof(r - 24 + (s === 6 ? 7 : 0)), type: "triangle", t, a: 0.01, d: sp * 3, g: 0.22 });
    // the melody
    const n = ph[s];
    if (n !== null && n !== undefined && n !== -1) {
      const len = (() => { let k = 1; while (s + k < 16 && ph[s + k] === null) k++; return k; })(), m = deg(r + 12, n);
      if (song.lead === "oud") { oud(musicBus, m, t, sp * Math.min(len, 3) + 0.1); if (len >= 2) oud(musicBus, m, t + sp, sp, 0.04); } // a tremolo pluck on long notes
      else if (song.lead === "ney") ney(musicBus, m, t, sp * len + 0.2);
      else tone(musicBus, { f: mtof(m + 12), type: "triangle", t, d: sp * 6, g: 0.07, echo: 0.7 }); // a harp
      lastNote = m;
    }
    // the ney answers the oud now and then
    if (song.ney && song.ney.includes(song.phrases[bar % song.phrases.length]) && s === 0 && bar % 8 === 2) ney(musicBus, deg(r + 24, 4), t, sp * 8, 0.05);
    step++; nextT += sp;
  }
}

export const sound = {
  // call from a tap: starts the audio (browsers require it), then whatever music was asked for
  unlock() { if (!init()) return; if (ctx.state === "suspended") ctx.resume(); if (songName && !song) this.music(songName, true); },
  play(name) { if (!ctx || ctx.state !== "running" || vol.sfx <= 0) return; try { FX[name]?.(); } catch (e) { console.warn("sound", name, e); } },
  music(name, force) {
    if (name === songName && !force) return;
    songName = name;
    if (!ctx || ctx.state !== "running") { song = null; return; }
    // the old song fades out, the new one starts on the next beat
    const old = musicBus; if (song) { old.gain.setTargetAtTime(0, now(), 0.4); musicBus = ctx.createGain(); musicBus.gain.value = 0; musicBus.connect(master); musicBus.gain.setTargetAtTime(vol.music, now() + 0.6, 0.5); setTimeout(() => old.disconnect(), 3000); }
    song = SONGS[name] || null; step = 0; nextT = now() + 0.1;
    clearInterval(timer); if (song) timer = setInterval(schedule, 50);
  },
  get volumes() { return { ...vol }; },
  setVolume(kind, v) {
    vol[kind] = v; try { localStorage.setItem("arduel." + kind + "2", String(v)); } catch {}
    if (!ctx) return; (kind === "music" ? musicBus : sfxBus).gain.setTargetAtTime(v, now(), 0.05);
  },
};
document.addEventListener("visibilitychange", () => { if (!ctx) return; document.hidden ? ctx.suspend() : ctx.resume(); });
