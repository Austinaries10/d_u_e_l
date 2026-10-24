// Music and sound effects, all made in the browser with the Web Audio API (no sound files to download).
// sound.play(name) for an effect, sound.music(scene) for the background music: "menu", "duel", "pack" or null.
// Browsers only allow sound after the first tap, so nothing plays until sound.unlock() is called from one.
let ctx = null, master, musicBus, sfxBus, noiseBuf = null, verb = null;
const store = (k, d) => { try { const v = localStorage.getItem("arduel." + k); return v == null ? d : +v; } catch { return d; } };
const vol = { music: store("music", 0.35), sfx: store("sfx", 0.7) };

function init() {
  if (ctx) return true;
  const AC = window.AudioContext || window.webkitAudioContext; if (!AC) return false;
  ctx = new AC();
  const comp = ctx.createDynamicsCompressor(); comp.threshold.value = -14; comp.ratio.value = 4; comp.connect(ctx.destination);
  master = ctx.createGain(); master.gain.value = 0.9; master.connect(comp);
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
function tone(bus, { f = 440, f2 = null, type = "sine", t = now(), a = 0.005, d = 0.2, g = 0.3, lp = 0, q = 0.7, detune = 0, echo = 0 }) {
  const o = ctx.createOscillator(), e = ctx.createGain(); o.type = type; o.frequency.setValueAtTime(f, t); o.detune.value = detune;
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
  activate: () => { [88, 95, 100].forEach((m, i) => tone(sfxBus, { f: mtof(m), t: now() + i * 0.06, d: 0.45, g: 0.09, echo: 0.6 })); noise(sfxBus, { f: 6000, d: 0.3, g: 0.05, echo: 0.4 }); },
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
  win: () => [[60, 0], [64, 0.15], [67, 0.3], [72, 0.45], [67, 0.75], [72, 0.9]].forEach(([m, t]) => { tone(sfxBus, { f: mtof(m), type: "sawtooth", t: now() + t, a: 0.01, d: t > 0.8 ? 1.2 : 0.25, g: 0.1, lp: 2500, echo: 0.5 }); tone(sfxBus, { f: mtof(m - 12), type: "triangle", t: now() + t, d: 0.3, g: 0.1 }); }),
  lose: () => [[67, 0], [63, 0.35], [60, 0.7], [55, 1.05]].forEach(([m, t]) => tone(sfxBus, { f: mtof(m), type: "sawtooth", t: now() + t, a: 0.02, d: t > 1 ? 1.4 : 0.4, g: 0.08, lp: 1400, echo: 0.5 })),
};

// ---------- Music: a little sequencer, one bar of 16 steps at a time ----------
// each scene: tempo, chords (root notes, minor or major), and which parts play
const SONGS = {
  menu: { bpm: 84, chords: [[57, "m"], [53, "M"], [48, "M"], [55, "M"]], pad: 1, arp: "slow", bass: "long", drums: 0 },
  duel: { bpm: 116, chords: [[50, "m"], [46, "M"], [41, "M"], [48, "M"], [50, "m"], [46, "M"], [43, "m"], [45, "M"]], pad: 0.7, arp: "fast", bass: "drive", drums: 1 },
  pack: { bpm: 96, chords: [[48, "M7"], [53, "M7"], [45, "m7"], [55, "M"]], pad: 1, arp: "sparkle", bass: "long", drums: 0 },
};
const SHAPE = { m: [0, 3, 7, 12], M: [0, 4, 7, 12], M7: [0, 4, 7, 11], m7: [0, 3, 7, 10] };
let song = null, songName = null, step = 0, nextT = 0, timer = null;
function schedule() {
  if (!song) return;
  const sp = 60 / song.bpm / 4; // a sixteenth
  if (nextT < now() - 0.2) nextT = now() + 0.05; // (back from the background: don't play the missed notes all at once)
  while (nextT < now() + 0.15) {
    const bar = Math.floor(step / 16), s = step % 16, [root, kind] = song.chords[bar % song.chords.length], sh = SHAPE[kind], t = nextT;
    if (s === 0 && song.pad) for (const iv of sh.slice(0, 3)) for (const dt of [-7, 7]) tone(musicBus, { f: mtof(root + 12 + iv), type: "sawtooth", t, a: 0.6, d: sp * 16 + 0.4, g: 0.035 * song.pad, lp: 900, detune: dt });
    if (song.bass === "long" && s === 0) tone(musicBus, { f: mtof(root - 12), type: "triangle", t, a: 0.05, d: sp * 15, g: 0.16 });
    if (song.bass === "drive" && s % 2 === 0) tone(musicBus, { f: mtof(root - 12 + (s === 6 || s === 14 ? 12 : 0)), type: "sawtooth", t, a: 0.005, d: sp * 1.6, g: 0.1, lp: 500, q: 4 });
    if (song.arp === "slow" && s % 4 === 0) tone(musicBus, { f: mtof(root + 24 + sh[(s / 4) % 4]), type: "triangle", t, d: sp * 6, g: 0.05, echo: 0.5 });
    if (song.arp === "fast" && s % 2 === 0) tone(musicBus, { f: mtof(root + 24 + sh[[0, 1, 2, 3, 2, 1, 2, 3][s / 2]]), type: "square", t, d: sp * 1.4, g: 0.025, lp: 2600, echo: 0.3 });
    if (song.arp === "sparkle" && (s % 3 === 0 || s === 14)) tone(musicBus, { f: mtof(root + 36 + sh[s % 4]), type: "sine", t, d: sp * 5, g: 0.04, echo: 0.8 });
    if (song.drums) {
      if (s % 4 === 0) tone(musicBus, { f: 120, f2: 40, t, d: 0.18, g: 0.5 });
      if (s === 4 || s === 12) noise(musicBus, { f: 1800, t, d: 0.14, g: 0.16, q: 0.6 });
      if (s % 2 === 1) noise(musicBus, { type: "highpass", f: 7000, t, d: 0.04, g: 0.05 });
      if (bar % 4 === 3 && s >= 12) noise(musicBus, { f: 1200 + s * 120, t, d: 0.08, g: 0.1 }); // a fill
    }
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
    vol[kind] = v; try { localStorage.setItem("arduel." + kind, String(v)); } catch {}
    if (!ctx) return; (kind === "music" ? musicBus : sfxBus).gain.setTargetAtTime(v, now(), 0.05);
  },
};
document.addEventListener("visibilitychange", () => { if (!ctx) return; document.hidden ? ctx.suspend() : ctx.resume(); });
