// The duel arenas, drawn in code (no downloads): the board sits on a rune-lit platform in a place that suits the rival:
// ancient ruins, Pegasus's castle, a Domino rooftop, the jungle, a harbour pier, a graveyard, the Egyptian desert,
// the Kaiba stadium, the Duel Academy island, a neon city at night or cyberspace.
// The board is about 0.43 × 0.67 (metres, real card size) and sits at the centre on y = 0; the opponent's side is -z.
import * as THREE from "three";

// a seeded random, so each arena looks the same every duel
let seed = 7;
const rnd = (a = 0, b = 1) => { seed = (seed * 16807) % 2147483647; return a + (b - a) * (seed / 2147483647); };

function canvasTex(w, h, draw, repeat) {
  const c = document.createElement("canvas"); c.width = w; c.height = h; draw(c.getContext("2d"), w, h);
  const t = new THREE.CanvasTexture(c); t.colorSpace = THREE.SRGBColorSpace; t.anisotropy = 8;
  if (repeat) { t.wrapS = t.wrapT = THREE.RepeatWrapping; t.repeat.set(repeat, repeat); }
  return t;
}
const speckle = (g, w, h, n, a0, a1) => { for (let i = 0; i < n; i++) { g.fillStyle = `rgba(${rnd() < 0.5 ? "0,0,0" : "255,240,210"},${rnd(a0, a1)})`; g.fillRect(rnd(0, w), rnd(0, h), rnd(1, 4), rnd(1, 4)); } };
// speckled stone, for walls and columns
const stoneTex = (base, repeat = 1) => canvasTex(256, 256, (g, w, h) => {
  g.fillStyle = base; g.fillRect(0, 0, w, h); speckle(g, w, h, 2600, 0.03, 0.12);
  g.strokeStyle = "rgba(40,25,10,.25)"; g.lineWidth = 1;
  for (let i = 0; i < 8; i++) { g.beginPath(); let x = rnd(0, w), y = rnd(0, h); g.moveTo(x, y); for (let k = 0; k < 5; k++) { x += rnd(-18, 18); y += rnd(-18, 18); g.lineTo(x, y); } g.stroke(); }
}, repeat);
// the ground: flagstones, grass, sand, wooden planks, metal plates, asphalt with lines, a glowing grid...
function floorTex(style) {
  return canvasTex(512, 512, (g, w, h) => {
    if (style === "stone" || style === "darkstone") {
      const dark = style === "darkstone";
      g.fillStyle = dark ? "#141218" : "#2a2622"; g.fillRect(0, 0, w, h);
      const rows = 8;
      for (let r = 0; r < rows; r++) {
        let x = -rnd(0, 40); const y = r * h / rows;
        while (x < w) {
          const sw = rnd(40, 90), l = dark ? rnd(12, 22) : rnd(22, 38);
          g.fillStyle = dark ? `hsl(${rnd(250, 280)},${rnd(5, 12)}%,${l}%)` : `hsl(${rnd(22, 34)},${rnd(8, 16)}%,${l}%)`;
          g.beginPath(); g.moveTo(x + 3 + rnd(-2, 2), y + 3); g.lineTo(x + sw - 3, y + 3 + rnd(-2, 2)); g.lineTo(x + sw - 3 + rnd(-2, 2), y + h / rows - 3); g.lineTo(x + 3, y + h / rows - 3 + rnd(-2, 2)); g.closePath(); g.fill();
          for (let i = 0; i < 60; i++) { g.fillStyle = `rgba(0,0,0,${rnd(0.04, 0.14)})`; g.fillRect(x + rnd(4, sw - 6), y + rnd(4, h / rows - 6), rnd(1, 5), rnd(1, 5)); }
          if (rnd() < 0.35) { g.strokeStyle = "rgba(20,14,8,.55)"; g.lineWidth = 1.2; g.beginPath(); let cx = x + rnd(6, sw - 6), cy = y + 4; g.moveTo(cx, cy); while (cy < y + h / rows - 6) { cx += rnd(-6, 6); cy += rnd(4, 9); g.lineTo(cx, cy); } g.stroke(); }
          if (rnd() < 0.18) { g.fillStyle = `rgba(70,110,50,${rnd(0.25, 0.5)})`; for (let i = 0; i < 14; i++) g.fillRect(x + rnd(0, sw), y + h / rows - rnd(2, 8), rnd(2, 5), rnd(1, 3)); }
          x += sw;
        }
      }
    } else if (style === "grass" || style === "jungle") {
      const j = style === "jungle";
      g.fillStyle = j ? "#1f3518" : "#3f6a2c"; g.fillRect(0, 0, w, h);
      for (let i = 0; i < 9000; i++) { g.fillStyle = `hsla(${rnd(80, 120)},${rnd(35, 60)}%,${rnd(j ? 10 : 22, j ? 26 : 42)}%,${rnd(0.4, 0.9)})`; g.fillRect(rnd(0, w), rnd(0, h), rnd(1, 2), rnd(2, 6)); }
      for (let i = 0; i < 26; i++) { g.fillStyle = `rgba(${j ? "60,40,20" : "120,100,60"},${rnd(0.15, 0.35)})`; g.beginPath(); g.ellipse(rnd(0, w), rnd(0, h), rnd(10, 40), rnd(6, 20), rnd(0, 3), 0, 7); g.fill(); }
    } else if (style === "sand") {
      g.fillStyle = "#c9a26a"; g.fillRect(0, 0, w, h);
      for (let i = 0; i < 40; i++) { g.strokeStyle = `rgba(150,110,60,${rnd(0.15, 0.3)})`; g.lineWidth = rnd(2, 5); g.beginPath(); const y = rnd(0, h); g.moveTo(0, y); for (let x = 0; x <= w; x += 32) g.lineTo(x, y + Math.sin(x / 40 + i) * 6); g.stroke(); }
      speckle(g, w, h, 5000, 0.04, 0.12);
    } else if (style === "wood") {
      for (let x = 0; x < w; x += 64) {
        g.fillStyle = `hsl(${rnd(24, 32)},${rnd(30, 45)}%,${rnd(24, 34)}%)`; g.fillRect(x, 0, 62, h);
        for (let i = 0; i < 30; i++) { g.strokeStyle = `rgba(30,15,5,${rnd(0.1, 0.3)})`; g.beginPath(); const xx = x + rnd(2, 60); g.moveTo(xx, 0); g.bezierCurveTo(xx + rnd(-6, 6), h / 3, xx + rnd(-6, 6), h * 2 / 3, xx + rnd(-3, 3), h); g.stroke(); }
        g.fillStyle = "#120a04"; g.fillRect(x + 62, 0, 2, h); for (const y of [rnd(40, 200), rnd(260, 480)]) { g.fillRect(x, y, 64, 2); g.fillStyle = "#555"; g.fillRect(x + 8, y + 6, 3, 3); g.fillRect(x + 52, y + 6, 3, 3); g.fillStyle = "#120a04"; }
      }
    } else if (style === "concrete") {
      g.fillStyle = "#56524e"; g.fillRect(0, 0, w, h); speckle(g, w, h, 7000, 0.03, 0.1);
      g.strokeStyle = "rgba(20,20,20,.45)"; g.lineWidth = 2; for (let i = 0; i <= 4; i++) { g.beginPath(); g.moveTo(i * w / 4, 0); g.lineTo(i * w / 4, h); g.stroke(); g.beginPath(); g.moveTo(0, i * h / 4); g.lineTo(w, i * h / 4); g.stroke(); }
      for (let i = 0; i < 6; i++) { g.fillStyle = `rgba(30,30,30,${rnd(0.1, 0.25)})`; g.beginPath(); g.ellipse(rnd(0, w), rnd(0, h), rnd(10, 50), rnd(8, 30), 0, 0, 7); g.fill(); }
    } else if (style === "metal") {
      g.fillStyle = "#1c2433"; g.fillRect(0, 0, w, h);
      for (let y = 0; y < h; y += 128) for (let x = 0; x < w; x += 128) {
        const gr = g.createLinearGradient(x, y, x + 128, y + 128); gr.addColorStop(0, "#2c3850"); gr.addColorStop(1, "#18202e"); g.fillStyle = gr; g.fillRect(x + 2, y + 2, 124, 124);
        g.fillStyle = "#4a5a78"; for (const [a, b] of [[8, 8], [116, 8], [8, 116], [116, 116]]) g.fillRect(x + a, y + b, 4, 4);
      }
      g.shadowColor = "#3aa0ff"; g.shadowBlur = 10; g.strokeStyle = "rgba(80,170,255,.7)"; g.lineWidth = 2; g.beginPath(); g.moveTo(0, h / 2); g.lineTo(w, h / 2); g.moveTo(w / 2, 0); g.lineTo(w / 2, h); g.stroke();
    } else if (style === "asphalt") {
      g.fillStyle = "#16161c"; g.fillRect(0, 0, w, h); speckle(g, w, h, 9000, 0.03, 0.09);
      g.shadowBlur = 12; for (const [c, y] of [["#ff3df0", 128], ["#3de8ff", 384]]) { g.shadowColor = c; g.strokeStyle = c; g.lineWidth = 3; g.setLineDash([40, 30]); g.beginPath(); g.moveTo(0, y); g.lineTo(w, y); g.stroke(); }
      g.setLineDash([]);
      for (let i = 0; i < 8; i++) { g.shadowBlur = 0; g.fillStyle = `rgba(${rnd() < 0.5 ? "255,60,240" : "60,230,255"},.07)`; g.beginPath(); g.ellipse(rnd(0, w), rnd(0, h), rnd(20, 60), rnd(10, 30), 0, 0, 7); g.fill(); }
    } else if (style === "grid") {
      g.fillStyle = "#03060f"; g.fillRect(0, 0, w, h);
      g.shadowColor = "#2ef3ff"; g.shadowBlur = 8; g.strokeStyle = "rgba(46,243,255,.65)"; g.lineWidth = 2;
      for (let i = 0; i <= 8; i++) { g.beginPath(); g.moveTo(i * w / 8, 0); g.lineTo(i * w / 8, h); g.stroke(); g.beginPath(); g.moveTo(0, i * h / 8); g.lineTo(w, i * h / 8); g.stroke(); }
      g.fillStyle = "rgba(46,243,255,.25)"; for (let i = 0; i < 10; i++) g.fillRect(Math.floor(rnd(0, 8)) * w / 8 + 3, Math.floor(rnd(0, 8)) * h / 8 + 3, w / 8 - 6, h / 8 - 6);
    }
  }, style === "wood" ? 6 : style === "grid" ? 9 : 7);
}
// the stone platform the board sits on, with a glowing ring of runes around its edge
const platformTex = (rune, base = "#3a342d") => canvasTex(1024, 1024, (g, w, h) => {
  const c = w / 2;
  g.fillStyle = base; g.fillRect(0, 0, w, h); speckle(g, w, h, 9000, 0.02, 0.08);
  g.strokeStyle = "rgba(15,10,5,.5)"; g.lineWidth = 2;
  for (let i = 1; i < 6; i++) { g.beginPath(); g.moveTo(0, i * h / 6); g.lineTo(w, i * h / 6); g.stroke(); g.beginPath(); g.moveTo(i * w / 6 + (i % 2) * 20, 0); g.lineTo(i * w / 6 + (i % 2) * 20, h); g.stroke(); }
  g.shadowColor = rune; g.shadowBlur = 16; g.strokeStyle = rune;
  for (const [r, lw] of [[c - 30, 5], [c - 86, 3]]) { g.lineWidth = lw; g.beginPath(); g.arc(c, c, r, 0, 7); g.stroke(); }
  g.font = "34px serif"; g.fillStyle = rune; g.textAlign = "center"; g.textBaseline = "middle";
  const runes = "ᚠᚢᚦᚨᚱᚲᚷᚹᚺᚾᛁᛃᛇᛈᛉᛊᛏᛒᛖᛗᛚᛜᛞᛟ";
  for (let i = 0; i < 48; i++) { const a = i / 48 * Math.PI * 2; g.save(); g.translate(c + Math.cos(a) * (c - 58), c + Math.sin(a) * (c - 58)); g.rotate(a + Math.PI / 2); g.fillText(runes[i % runes.length], 0, 0); g.restore(); }
  g.lineWidth = 2; g.globalAlpha = 0.5;
  for (let i = 0; i < 4; i++) { const a = i * Math.PI / 2 + Math.PI / 4; g.beginPath(); g.moveTo(c + Math.cos(a) * (c - 86), c + Math.sin(a) * (c - 86)); g.lineTo(c + Math.cos(a) * (c - 150), c + Math.sin(a) * (c - 150)); g.stroke(); }
});
// the sky all around, horizon at eye level (the middle of the picture)
const skyTex = stops => canvasTex(16, 512, (g, w, h) => {
  const gr = g.createLinearGradient(0, 0, 0, h); for (const [k, c] of stops) gr.addColorStop(k, c);
  g.fillStyle = gr; g.fillRect(0, 0, w, h);
});
const glowTex = (inner, outer) => canvasTex(128, 128, (g, w, h) => {
  const gr = g.createRadialGradient(w / 2, h / 2, 0, w / 2, h / 2, w / 2); gr.addColorStop(0, inner); gr.addColorStop(0.35, outer); gr.addColorStop(1, "rgba(0,0,0,0)");
  g.fillStyle = gr; g.fillRect(0, 0, w, h);
});
// lit windows for city buildings
const windowsTex = (lit, base = "#11131c") => canvasTex(128, 256, (g, w, h) => {
  g.fillStyle = base; g.fillRect(0, 0, w, h);
  for (let y = 6; y < h - 6; y += 14) for (let x = 6; x < w - 6; x += 14) { g.fillStyle = rnd() < 0.45 ? lit[Math.floor(rnd(0, lit.length))] : "rgba(255,255,255,.04)"; g.fillRect(x, y, 8, 9); }
});

// Who duels where: each rival's home ground (anyone not listed duels in the ruins)
const HOMES = {
  castle: ["pegasus", "mai", "paradox", "keith"],
  rooftop: ["joey", "tea", "tristan", "espa"],
  jungle: ["weevil", "rex", "hassleberry"],
  pier: ["mako", "shark"],
  graveyard: ["bakura", "bonz", "lumis", "yubel"],
  egypt: ["marik", "ishizu", "odion", "yami"],
  stadium: ["kaiba", "zane", "declan"],
};
const GATE_HOME = { gx: "academy", "5ds": "neon", zexal: "neon", arcv: "neon", vrains: "cyber" };
export function arenaFor(charId, gateId) {
  for (const [k, list] of Object.entries(HOMES)) if (list.includes(charId)) return k;
  return GATE_HOME[gateId] || "ruins";
}

// What each place looks like: sky, haze, ground, the platform's runes, the drifting particles and the light
const LOOKS = {
  ruins: { plat: "#3a342d", sky: [[0, "#0b1530"], [0.3, "#2b2f5c"], [0.44, "#7a5a7e"], [0.495, "#e29a6a"], [0.515, "#3b3346"], [1, "#1d1a22"]], fog: 0x3b3346, floor: "stone", rune: "rgba(120,240,255,.6)", dust: ["rgba(255,230,180,1)", "rgba(255,170,90,.4)", 1], rim: 0xffc48a },
  castle: { plat: "#5f5c55", sky: [[0, "#2d6fd1"], [0.38, "#7fb6f0"], [0.495, "#e8f1ff"], [0.515, "#7c9a6a"], [1, "#2e3d25"]], fog: 0x9fb8d0, floor: "grass", rune: "rgba(255,220,120,.65)", dust: ["rgba(255,255,255,1)", "rgba(255,240,200,.3)", 1], rim: 0xfff2d8 },
  rooftop: { plat: "#4c4a48", sky: [[0, "#1b1d4a"], [0.3, "#6a3f7a"], [0.44, "#ff7a59"], [0.495, "#ffc46b"], [0.515, "#3a2b3a"], [1, "#16131c"]], fog: 0x6a4a5a, floor: "concrete", rune: "rgba(255,170,90,.65)", dust: null, rim: 0xffa070 },
  jungle: { plat: "#36442a", sky: [[0, "#0f2a1e"], [0.35, "#2f5a3a"], [0.495, "#a8c890"], [0.515, "#2a3a20"], [1, "#0d160a"]], fog: 0x3c5a3a, floor: "jungle", rune: "rgba(170,255,120,.6)", dust: ["rgba(220,255,140,1)", "rgba(150,255,80,.45)", 1], rim: 0xd8ffb0 },
  pier: { plat: "#5a3e26", sky: [[0, "#1f4f9a"], [0.38, "#6aa7e0"], [0.495, "#ffe1b0"], [0.515, "#2a5a7a"], [1, "#0d2a3d"]], fog: 0x8fb4cf, floor: "wood", rune: "rgba(120,220,255,.65)", dust: ["rgba(255,255,255,1)", "rgba(200,240,255,.3)", 1], rim: 0xfff0d0 },
  graveyard: { plat: "#26222c", sky: [[0, "#05030c"], [0.35, "#1c1033"], [0.495, "#4a2d5e"], [0.515, "#150f1c"], [1, "#07050a"]], fog: 0x241a33, floor: "darkstone", rune: "rgba(170,120,255,.7)", dust: ["rgba(170,255,200,1)", "rgba(80,255,160,.4)", 1], rim: 0x9a8cff },
  egypt: { plat: "#6e5232", sky: [[0, "#2a3f7a"], [0.32, "#c47a4a"], [0.46, "#ffb060"], [0.495, "#ffe0a0"], [0.515, "#a87a48"], [1, "#5a3f22"]], fog: 0xd8a868, floor: "sand", rune: "rgba(255,215,90,.7)", dust: ["rgba(255,220,160,1)", "rgba(255,190,110,.35)", 1], rim: 0xffd090 },
  stadium: { plat: "#1c2433", sky: [[0, "#02040c"], [0.4, "#0a1430"], [0.495, "#1d3560"], [0.515, "#0b1222"], [1, "#04060c"]], fog: 0x0e1a33, floor: "metal", rune: "rgba(80,190,255,.75)", dust: ["rgba(160,220,255,1)", "rgba(60,150,255,.4)", 1], rim: 0x8cc8ff },
  academy: { plat: "#6a6e74", sky: [[0, "#1f78d6"], [0.38, "#7cc3f5"], [0.495, "#e6f6ff"], [0.505, "#3aa0c8"], [0.53, "#2c7aa0"], [1, "#0f3a55"]], fog: 0xa8d4ee, floor: "grass", rune: "rgba(255,200,90,.65)", dust: ["rgba(255,255,255,1)", "rgba(255,255,220,.3)", 1], rim: 0xfff6dc },
  neon: { plat: "#1c1626", sky: [[0, "#05020f"], [0.36, "#1a0b33"], [0.48, "#5a1a6a"], [0.495, "#ff4fb0"], [0.515, "#120a1e"], [1, "#05030a"]], fog: 0x2a1240, floor: "asphalt", rune: "rgba(255,80,220,.75)", dust: ["rgba(120,240,255,1)", "rgba(80,200,255,.35)", -1], rim: 0xff7ae0 },
  cyber: { plat: "#0a1622", sky: [[0, "#000208"], [0.4, "#021a2e"], [0.495, "#0a5a7a"], [0.515, "#01101c"], [1, "#000308"]], fog: 0x032036, floor: "grid", rune: "rgba(60,255,240,.8)", dust: ["rgba(120,255,255,1)", "rgba(40,220,255,.45)", 1], rim: 0x5ff6ff },
};

export function buildArena(theme = "ruins") {
  seed = 7;
  const look = LOOKS[theme] || LOOKS.ruins;
  const arena = new THREE.Group(), animated = [];
  const sand = new THREE.MeshStandardMaterial({ map: stoneTex("#b59a72"), roughness: 0.9 });
  const darkStone = new THREE.MeshStandardMaterial({ map: stoneTex("#6d6255", 2), roughness: 0.95 });
  const add = o => (arena.add(o), o);
  const box = (x, y, z, w, h, d, m) => { const b = new THREE.Mesh(new THREE.BoxGeometry(w, h, d), m); b.position.set(x, y, z); return add(b); };
  const ctx = { arena, animated, add, box, sand, darkStone };

  // the ground, with the board's platform a step above it
  add(new THREE.Mesh(new THREE.PlaneGeometry(9, 9).rotateX(-Math.PI / 2), new THREE.MeshStandardMaterial({ map: floorTex(look.floor), roughness: look.floor === "metal" ? 0.5 : 0.95, metalness: look.floor === "metal" ? 0.4 : 0 }))).position.y = -0.03;
  const platMap = platformTex(look.rune, look.plat);
  const plat = add(new THREE.Mesh(new THREE.CylinderGeometry(0.56, 0.6, 0.028, 64), [new THREE.MeshStandardMaterial({ map: stoneTex("#4a4239", 4), roughness: 0.9 }), new THREE.MeshStandardMaterial({ map: platMap, roughness: 0.85 }), darkStone]));
  plat.position.y = -0.016;
  // the runes breathe: a glow layer just above the platform
  const runeGlow = add(new THREE.Mesh(new THREE.CircleGeometry(0.56, 64).rotateX(-Math.PI / 2), new THREE.MeshBasicMaterial({ map: platMap, transparent: true, opacity: 0.5, blending: THREE.AdditiveBlending, depthWrite: false })));
  runeGlow.position.y = -0.0015; runeGlow.renderOrder = -2;
  animated.push(t => { runeGlow.material.opacity = 0.16 + Math.sin(t * 1.3) * 0.07; });

  (SCENERY[theme] || SCENERY.ruins)(ctx);

  // drifting particles: embers, fireflies, wisps, sea spray, data... (rising, or falling like rain when the speed is negative)
  if (look.dust) {
    const [inner, outer, dir] = look.dust, N = 160, pts = new Float32Array(N * 3), speed = new Float32Array(N);
    for (let i = 0; i < N; i++) { pts.set([rnd(-1.1, 1.1), rnd(0, 0.6), rnd(-1.1, 0.8)], i * 3); speed[i] = rnd(0.008, 0.03) * (dir < 0 ? -6 : 1); }
    const geo = new THREE.BufferGeometry(); geo.setAttribute("position", new THREE.BufferAttribute(pts, 3));
    add(new THREE.Points(geo, new THREE.PointsMaterial({ size: dir < 0 ? 0.006 : 0.012, map: glowTex(inner, outer), transparent: true, blending: THREE.AdditiveBlending, depthWrite: false, opacity: 0.8 })));
    let last = 0;
    animated.push(t => { const dt = Math.min(0.05, t - last); last = t; for (let i = 0; i < N; i++) { let y = pts[i * 3 + 1] + speed[i] * dt; if (y > 0.6) y = 0; if (y < 0) y = 0.6; pts[i * 3 + 1] = y; pts[i * 3] += Math.sin(t + i) * 0.0004; } geo.attributes.position.needsUpdate = true; });
  }
  const sky = add(new THREE.Mesh(new THREE.SphereGeometry(8, 32, 16), new THREE.MeshBasicMaterial({ map: skyTex(look.sky), side: THREE.BackSide, fog: false, depthWrite: false })));
  sky.renderOrder = -10;
  const rim = add(new THREE.DirectionalLight(look.rim, 0.7)); rim.position.set(-0.4, 0.6, -1);
  return { group: arena, fog: new THREE.Fog(look.fog, 2.2, 6.5), theme, update: t => { for (const f of animated) f(t); } };
}

// ---------- Scenery for each place ----------
const std = (color, o = {}) => new THREE.MeshStandardMaterial({ color, roughness: 0.85, ...o });
const glowMat = (color, opacity = 1) => new THREE.MeshBasicMaterial({ color, transparent: opacity < 1, opacity, fog: false });
// a fluted column; broken ones have a jagged top
function column({ add }, m, x, z, h, broken = false, r = 0.034) {
  const g = new THREE.Group();
  const geo = new THREE.CylinderGeometry(r * 0.9, r, h, 20, 6, false);
  const pos = geo.attributes.position, v = new THREE.Vector3();
  for (let i = 0; i < pos.count; i++) {
    v.fromBufferAttribute(pos, i); const a = Math.atan2(v.z, v.x), k = 1 - 0.07 * Math.max(0, Math.cos(a * 10));
    v.x *= k; v.z *= k;
    if (broken && v.y > h / 2 - 0.001) v.y -= rnd(0, h * 0.25);
    pos.setXYZ(i, v.x, v.y, v.z);
  }
  geo.computeVertexNormals();
  const shaft = new THREE.Mesh(geo, m); shaft.position.y = h / 2 + 0.02; g.add(shaft);
  const base = new THREE.Mesh(new THREE.BoxGeometry(r * 2.8, 0.02, r * 2.8), m); base.position.y = 0.01; g.add(base);
  if (!broken) { const cap = new THREE.Mesh(new THREE.BoxGeometry(r * 2.8, 0.022, r * 2.8), m); cap.position.y = h + 0.031; g.add(cap); }
  g.position.set(x, -0.03, z); g.rotation.y = rnd(0, 6); add(g); return g;
}
const rocks = ({ add }, m, n, d0 = 0.66, d1 = 1.5) => { for (let i = 0; i < n; i++) { const a = rnd(0, Math.PI * 2), d = rnd(d0, d1), s = rnd(0.008, 0.028); const r = new THREE.Mesh(new THREE.DodecahedronGeometry(s, 0), m); r.position.set(Math.cos(a) * d * 1.2, -0.03 + s * 0.4, Math.sin(a) * d); r.rotation.set(rnd(0, 3), rnd(0, 3), rnd(0, 3)); r.scale.y = rnd(0.5, 0.9); add(r); } };
// a flame on a stand, with a flickering light
function brazier({ add, animated }, x, z, color = 0xff9a4a, inner = "rgba(255,240,180,1)", outer = "rgba(255,120,30,.6)", h = 0.1) {
  const bowl = add(new THREE.Mesh(new THREE.CylinderGeometry(0.035, 0.018, 0.03, 16, 1, true), std(0x6b4b2a, { metalness: 0.6, roughness: 0.4, side: THREE.DoubleSide })));
  bowl.position.set(x, h, z);
  add(new THREE.Mesh(new THREE.CylinderGeometry(0.008, 0.016, h, 8), bowl.material)).position.set(x, h / 2 - 0.015, z);
  const flame = add(new THREE.Sprite(new THREE.SpriteMaterial({ map: glowTex(inner, outer), blending: THREE.AdditiveBlending, depthWrite: false }))); flame.position.set(x, h + 0.035, z);
  const light = add(new THREE.PointLight(color, 0.6, 1.4, 1.6)); light.position.set(x, h + 0.08, z);
  const s = rnd(0, 6);
  animated.push(t => { const f = 0.85 + Math.sin(t * 13 + s) * 0.08 + Math.sin(t * 7.3 + s) * 0.07; flame.scale.set(0.09 * f, 0.12 * f, 1); light.intensity = 0.6 * f; });
}
// a cloth banner that waves in the wind
function banner({ add, animated }, x, y, z, color, w = 0.08, h = 0.26) {
  const b = add(new THREE.Mesh(new THREE.PlaneGeometry(w, h, 1, 8), std(color, { side: THREE.DoubleSide, emissive: new THREE.Color(color).multiplyScalar(0.25) })));
  b.geometry.translate(0, -h / 2, 0); b.position.set(x, y, z);
  const bp = b.geometry.attributes.position, by = Float32Array.from({ length: bp.count }, (_, i) => bp.getY(i)), s = rnd(0, 6);
  animated.push(t => { for (let i = 0; i < bp.count; i++) bp.setZ(i, Math.sin(t * 1.6 + by[i] * 22 + s) * 0.006 * -by[i] * 5); bp.needsUpdate = true; });
  return b;
}
const hills = ({ add }, color, n = 9, z0 = -4.4, z1 = -2.6) => { const m = std(color, { roughness: 1, flatShading: true }); for (let i = 0; i < n; i++) { const h = add(new THREE.Mesh(new THREE.SphereGeometry(rnd(1, 1.8), 10, 6, 0, Math.PI * 2, 0, Math.PI / 2), m)); h.scale.y = rnd(0.22, 0.4); h.position.set(rnd(-4, 4), -0.05, rnd(z0, z1)); } };
const pines = ({ add }, color, n = 40, d0 = 1.5, d1 = 3) => { const m = std(color, { roughness: 1, flatShading: true }); for (let i = 0; i < n; i++) { const a = rnd(-Math.PI * 0.95, -Math.PI * 0.05), d = rnd(d0, d1), h = rnd(0.25, 0.55); add(new THREE.Mesh(new THREE.ConeGeometry(h * 0.28, h, 6), m)).position.set(Math.cos(a) * d * 1.3, h / 2 - 0.02, Math.sin(a) * d); } };
function palm({ add, animated }, x, z, h = 0.5) {
  const trunk = add(new THREE.Mesh(new THREE.CylinderGeometry(0.008, 0.014, h, 7), std(0x7a5a38)));
  trunk.position.set(x, h / 2 - 0.03, z); trunk.rotation.z = rnd(-0.15, 0.15);
  const leaf = std(0x2f8a3a, { side: THREE.DoubleSide }), top = new THREE.Group(); top.position.set(x + trunk.rotation.z * -h / 2, h - 0.03, z); add(top);
  for (let i = 0; i < 7; i++) { const l = new THREE.Mesh(new THREE.PlaneGeometry(0.05, 0.2).translate(0, 0.1, 0), leaf); l.rotation.set(-1.1, i / 7 * Math.PI * 2, 0, "YXZ"); top.add(l); }
  const s = rnd(0, 6); animated.push(t => { top.rotation.z = Math.sin(t * 0.8 + s) * 0.05; });
}
// a ring of city buildings with lit windows
function city({ add }, lit, n = 34, d0 = 2.2, d1 = 3.6, base) {
  const mats = [0, 1, 2].map(() => new THREE.MeshStandardMaterial({ map: windowsTex(lit, base), emissive: 0xffffff, emissiveMap: null, roughness: 0.8 }));
  for (const m of mats) { m.emissiveMap = m.map; m.emissiveIntensity = 0.6; }
  for (let i = 0; i < n; i++) {
    const a = rnd(-Math.PI * 0.98, -Math.PI * 0.02), d = rnd(d0, d1), w = rnd(0.25, 0.5), h = rnd(0.5, 1.8);
    const b = add(new THREE.Mesh(new THREE.BoxGeometry(w, h, w), mats[i % 3])); b.position.set(Math.cos(a) * d * 1.3, h / 2 - 0.03, Math.sin(a) * d); b.rotation.y = rnd(0, 1);
  }
}
function water({ add, animated }, color, y = -0.06) {
  const geo = new THREE.PlaneGeometry(14, 14, 60, 60).rotateX(-Math.PI / 2), m = new THREE.MeshStandardMaterial({ color, roughness: 0.25, metalness: 0.3, transparent: true, opacity: 0.92 });
  const w = add(new THREE.Mesh(geo, m)); w.position.y = y;
  const p = geo.attributes.position, x0 = Float32Array.from({ length: p.count }, (_, i) => p.getX(i)), z0 = Float32Array.from({ length: p.count }, (_, i) => p.getZ(i));
  let k = 0; animated.push(t => { if (++k % 2) return; for (let i = 0; i < p.count; i++) p.setY(i, Math.sin(x0[i] * 3 + t * 1.2) * 0.012 + Math.cos(z0[i] * 2.5 + t) * 0.01); p.needsUpdate = true; geo.computeVertexNormals(); });
}

const SCENERY = {
  // an old stone courtyard: a gate with stairs and an arch, columns with banners, braziers, broken columns, pines and a castle far away
  ruins(c) {
    const { add, box, sand, darkStone, animated } = c, back = -1.05;
    for (let i = 0; i < 6; i++) box(0, -0.03 + 0.012 + i * 0.024, back + 0.17 - i * 0.03, 0.42, 0.024, 0.06, darkStone);
    for (const s of [-1, 1]) {
      box(s * 0.72, 0.02, back, 0.95, 0.1, 0.07, darkStone);
      for (let i = 0; i < 9; i++) box(s * (0.3 + i * 0.1 + rnd(-0.01, 0.01)), 0.075 + (i % 3 === 2 ? -0.02 : 0), back, 0.095, 0.03, 0.065, sand);
    }
    const archR = 0.2, archY = 0.16;
    for (const s of [-1, 1]) box(s * archR, archY / 2 + 0.02, back - 0.06, 0.05, archY + 0.04, 0.05, sand);
    for (let i = 0; i <= 8; i++) { const a = i / 8 * Math.PI; box(Math.cos(a) * archR, archY + 0.04 + Math.sin(a) * archR, back - 0.06, 0.05, 0.035, 0.05, sand).rotation.z = a - Math.PI / 2; }
    const portal = add(new THREE.Mesh(new THREE.CircleGeometry(archR - 0.02, 32, 0, Math.PI), new THREE.MeshBasicMaterial({ map: glowTex("rgba(160,240,255,.9)", "rgba(60,140,255,.35)"), transparent: true, blending: THREE.AdditiveBlending, depthWrite: false })));
    portal.position.set(0, archY + 0.04, back - 0.062);
    add(new THREE.Mesh(new THREE.PlaneGeometry((archR - 0.02) * 2, archY + 0.04), portal.material)).position.set(0, (archY + 0.04) / 2, back - 0.062);
    animated.push(t => { portal.material.opacity = 0.55 + Math.sin(t * 2.1) * 0.15; });
    for (const s of [-1, 1]) { column(c, sand, s * 0.42, back + 0.26, 0.62, false, 0.042); box(s * 0.42, 0.62 + 0.035, back + 0.26, 0.16, 0.035, 0.1, sand); banner(c, s * 0.3, 0.64, back + 0.26, 0x3d2a6b); }
    for (const [x, z, h] of [[-0.66, -0.38, 0.26], [0.68, -0.34, 0.34], [-0.72, 0.3, 0.16], [0.7, 0.36, 0.2], [-1.0, -0.12, 0.42], [1.05, 0.05, 0.3]]) column(c, sand, x, z, h, true);
    for (const [x, z, h] of [[-0.9, -0.62, 0.6], [0.92, -0.64, 0.6]]) column(c, sand, x, z, h, false, 0.042);
    { const f = add(new THREE.Mesh(new THREE.CylinderGeometry(0.03, 0.033, 0.26, 18), sand)); f.rotation.set(0, 0.5, Math.PI / 2); f.position.set(-0.72, 0, 0); }
    rocks(c, sand, 46);
    for (const s of [-1, 1]) brazier(c, s * 0.6, back + 0.36);
    hills(c, 0x2f3d2c); pines(c, 0x1f3324);
    for (const [x, w, h] of [[0, 0.28, 0.36], [-0.2, 0.09, 0.5], [0.2, 0.09, 0.5]]) {
      box(x, h / 2 + 0.35, -3.1, w, h, w, darkStone);
      const roof = add(new THREE.Mesh(new THREE.ConeGeometry(w * 0.75, w * 1.1, 4), std(0x3b2f4a))); roof.position.set(x, h + 0.35 + w * 0.55, -3.1); roof.rotation.y = Math.PI / 4;
    }
  },
  // Duelist Kingdom: a castle courtyard on the island, walls and towers with flags, a path of stones and trees
  castle(c) {
    const { add, box, darkStone } = c, wall = new THREE.MeshStandardMaterial({ map: stoneTex("#9a9488", 3), roughness: 0.9 }), roofM = std(0x6a2a3a), back = -1.2;
    box(0, 0.2, back, 2.6, 0.46, 0.12, wall);
    for (let i = -12; i <= 12; i++) if (i % 2 === 0) box(i * 0.1, 0.46, back, 0.07, 0.06, 0.13, wall);
    for (const x of [-1.3, -0.45, 0.45, 1.3]) {
      const r = x === -0.45 || x === 0.45 ? 0.12 : 0.15, h = x === -0.45 || x === 0.45 ? 0.95 : 0.75;
      add(new THREE.Mesh(new THREE.CylinderGeometry(r, r * 1.08, h, 18), wall)).position.set(x, h / 2 - 0.03, back + 0.02);
      add(new THREE.Mesh(new THREE.ConeGeometry(r * 1.25, r * 2.2, 18), roofM)).position.set(x, h - 0.03 + r * 1.1, back + 0.02);
      banner(c, x + 0.02, h + r * 2.2 + 0.08, back + 0.02, 0xc8a038, 0.07, 0.09);
      add(new THREE.Mesh(new THREE.CylinderGeometry(0.003, 0.003, 0.16, 5), std(0x333333))).position.set(x, h + r * 2.2, back + 0.02);
    }
    // the great door
    box(0, 0.12, back + 0.065, 0.3, 0.3, 0.02, std(0x4a2e18));
    add(new THREE.Mesh(new THREE.CylinderGeometry(0.15, 0.15, 0.021, 24, 1, false, 0, Math.PI), std(0x4a2e18))).position.set(0, 0.27, back + 0.065);
    // a stone path to the gate, and trees on both sides
    for (let i = 0; i < 8; i++) box(rnd(-0.05, 0.05), -0.028, -0.62 - i * 0.07, rnd(0.08, 0.12), 0.006, 0.05, darkStone);
    const leaf = std(0x2f6a2a, { flatShading: true }), trunk = std(0x5a3e22);
    for (const [x, z] of [[-0.85, -0.4], [0.9, -0.45], [-0.95, 0.35], [1.0, 0.3], [-0.7, -0.85], [0.75, -0.85]]) {
      add(new THREE.Mesh(new THREE.CylinderGeometry(0.012, 0.018, 0.16, 7), trunk)).position.set(x, 0.05, z);
      add(new THREE.Mesh(new THREE.IcosahedronGeometry(rnd(0.09, 0.13), 0), leaf)).position.set(x, 0.2, z);
    }
    hills(c, 0x4a6e3a, 9, -4.4, -2.6); pines(c, 0x2a4a2a, 30, 2, 3.4);
    for (const s of [-1, 1]) brazier(c, s * 0.55, -0.85, 0xffb060);
  },
  // a Domino City rooftop at sunset: railings, a water tank, vents, and the city all around
  rooftop(c) {
    const { add, box } = c, metal = std(0x8a8f99, { metalness: 0.6, roughness: 0.4 });
    for (const [x0, z0, x1, z1] of [[-1.1, -1, 1.1, -1], [-1.1, -1, -1.1, 0.9], [1.1, -1, 1.1, 0.9]]) {
      const len = Math.hypot(x1 - x0, z1 - z0), n = Math.round(len / 0.1), rail = box((x0 + x1) / 2, 0.14, (z0 + z1) / 2, x0 === x1 ? 0.01 : len, 0.01, x0 === x1 ? len : 0.01, metal);
      for (let i = 0; i <= n; i++) box(x0 + (x1 - x0) * i / n, 0.055, z0 + (z1 - z0) * i / n, 0.008, 0.17, 0.008, metal);
      rail.userData.r = 1;
    }
    // the water tank on legs, and the stair hut
    for (const [dx, dz] of [[-0.06, -0.06], [0.06, -0.06], [-0.06, 0.06], [0.06, 0.06]]) box(0.7 + dx, 0.08, -0.75 + dz, 0.01, 0.22, 0.01, metal);
    add(new THREE.Mesh(new THREE.CylinderGeometry(0.11, 0.11, 0.2, 18), std(0x6a4a3a))).position.set(0.7, 0.29, -0.75);
    add(new THREE.Mesh(new THREE.ConeGeometry(0.12, 0.06, 18), std(0x4a3a30))).position.set(0.7, 0.42, -0.75);
    box(-0.7, 0.1, -0.78, 0.3, 0.26, 0.22, std(0x6e6a64)); box(-0.7, 0.07, -0.66, 0.1, 0.18, 0.01, std(0x3a4a5a));
    for (const [x, z] of [[-0.9, 0.2], [0.95, -0.1], [0.9, 0.5]]) { box(x, 0.02, z, 0.1, 0.1, 0.1, metal); add(new THREE.Mesh(new THREE.CylinderGeometry(0.03, 0.03, 0.06, 12), metal)).position.set(x, 0.1, z); }
    city(c, ["#ffd27a", "#ffeab0", "#ffb070"], 40, 2, 3.6, "#1a1620");
  },
  // the jungle: giant trees, ferns and fireflies
  jungle(c) {
    const { add } = c, trunk = std(0x4a3420, { flatShading: true }), leaves = [0x24521f, 0x2f6a24, 0x1d4219].map(x => std(x, { flatShading: true }));
    for (let i = 0; i < 26; i++) {
      const a = rnd(-Math.PI * 1.05, Math.PI * 0.05), d = rnd(0.8, 2.2), x = Math.cos(a) * d * 1.2, z = Math.sin(a) * d, h = rnd(0.5, 1.1);
      if (Math.abs(x) < 0.7 && z > -0.8) continue;
      add(new THREE.Mesh(new THREE.CylinderGeometry(0.02, 0.04, h, 7), trunk)).position.set(x, h / 2 - 0.03, z);
      for (let k = 0; k < 4; k++) { const l = add(new THREE.Mesh(new THREE.IcosahedronGeometry(rnd(0.14, 0.24), 1), leaves[k % 3])); l.position.set(x + rnd(-0.1, 0.1), h - 0.04 + rnd(0, 0.12), z + rnd(-0.1, 0.1)); l.scale.y = 0.7; }
    }
    const fern = std(0x3a7a2a, { side: THREE.DoubleSide, flatShading: true });
    for (let i = 0; i < 60; i++) { const a = rnd(0, Math.PI * 2), d = rnd(0.68, 1.6), f = add(new THREE.Mesh(new THREE.IcosahedronGeometry(rnd(0.025, 0.05), 1), fern)); f.position.set(Math.cos(a) * d * 1.2, -0.02, Math.sin(a) * d); f.scale.y = 0.6; }
    rocks(c, std(0x5a5a4a), 20, 0.66, 1.4);
    // a mossy stone idol behind the opponent
    const moss = new THREE.MeshStandardMaterial({ map: stoneTex("#5a6a4a", 2), roughness: 1 });
    c.box(0, 0.12, -1.05, 0.22, 0.3, 0.16, moss); c.box(0, 0.32, -1.05, 0.16, 0.12, 0.14, moss);
    for (const s of [-1, 1]) add(new THREE.Mesh(new THREE.SphereGeometry(0.015, 8, 6), glowMat(0xa8ff60))).position.set(s * 0.035, 0.33, -0.97);
    for (const s of [-1, 1]) brazier(c, s * 0.5, -0.9, 0xb0ff70, "rgba(230,255,180,1)", "rgba(140,255,60,.55)");
  },
  // a harbour pier on the sea: wooden posts with ropes, a lighthouse and boats far away
  pier(c) {
    const { add, box } = c, wood = std(0x5a3e26);
    water(c, 0x1d5f8a);
    box(0, -0.04, 0, 2.4, 0.02, 2.2, std(0x4a3420));
    for (const x of [-1.15, -0.4, 0.4, 1.15]) for (const z of [-1.05, 1.05]) add(new THREE.Mesh(new THREE.CylinderGeometry(0.025, 0.025, 0.24, 10), wood)).position.set(x, 0.06, z);
    for (const z of [-1.05]) box(0, 0.12, z, 2.3, 0.008, 0.008, std(0xc8b48a));
    // the lighthouse, with its turning beam
    const lx = -2.2, lz = -3;
    add(new THREE.Mesh(new THREE.CylinderGeometry(0.12, 0.18, 1.1, 16), std(0xeeeeee))).position.set(lx, 0.5, lz);
    for (const y of [0.25, 0.65]) add(new THREE.Mesh(new THREE.CylinderGeometry(0.155, 0.16, 0.12, 16), std(0xc0392b))).position.set(lx, y, lz);
    const lamp = add(new THREE.Mesh(new THREE.SphereGeometry(0.08, 12, 8), glowMat(0xfff2b0))); lamp.position.set(lx, 1.12, lz);
    const beam = add(new THREE.Mesh(new THREE.ConeGeometry(0.25, 2.4, 16, 1, true).rotateZ(Math.PI / 2).translate(1.2, 0, 0), glowMat(0xfff2b0, 0.12))); beam.position.set(lx, 1.12, lz);
    c.animated.push(t => { beam.rotation.y = t * 0.6; });
    // boats bobbing far out
    for (const [x, z] of [[1.6, -2.6], [2.6, -3.4], [-0.6, -3.8]]) {
      const boat = new THREE.Group(), sail = new THREE.Mesh(new THREE.ConeGeometry(0.08, 0.25, 3), std(0xf4ead8, { side: THREE.DoubleSide }));
      sail.position.y = 0.15; boat.add(new THREE.Mesh(new THREE.BoxGeometry(0.3, 0.06, 0.1), std(0xffffff)), sail);
      boat.position.set(x, -0.03, z); add(boat); const s = rnd(0, 6); c.animated.push(t => { boat.position.y = -0.03 + Math.sin(t + s) * 0.01; boat.rotation.z = Math.sin(t * 0.8 + s) * 0.05; });
    }
    for (const s of [-1, 1]) brazier(c, s * 0.62, -0.92, 0xffd090);
  },
  // a graveyard at night: tombstones, crosses, bare trees, a huge moon and wisps
  graveyard(c) {
    const { add, box } = c, grave = new THREE.MeshStandardMaterial({ map: stoneTex("#6a6874", 1), roughness: 1 });
    for (let i = 0; i < 40; i++) {
      const a = rnd(-Math.PI * 1.05, Math.PI * 0.05), d = rnd(0.7, 1.9), x = Math.cos(a) * d * 1.25, z = Math.sin(a) * d;
      if (Math.abs(x) < 0.66 && z > -0.75) continue;
      if (rnd() < 0.7) { const h = rnd(0.08, 0.14), t = box(x, h / 2 - 0.03, z, rnd(0.06, 0.09), h, 0.02, grave); t.rotation.set(rnd(-0.12, 0.12), rnd(-0.4, 0.4), rnd(-0.1, 0.1)); add(new THREE.Mesh(new THREE.CylinderGeometry(0.035, 0.035, 0.02, 14, 1, false, 0, Math.PI), grave)).position.set(x, h - 0.03, z); }
      else { box(x, 0.05, z, 0.015, 0.16, 0.015, grave); box(x, 0.09, z, 0.08, 0.015, 0.015, grave); }
    }
    const bark = std(0x2a2228, { flatShading: true });
    const tree = (x, z, h) => { const g = new THREE.Group(); g.position.set(x, -0.03, z); add(g); const trunkM = new THREE.Mesh(new THREE.CylinderGeometry(0.012, 0.03, h, 6), bark); trunkM.position.y = h / 2; g.add(trunkM);
      for (let i = 0; i < 5; i++) { const b = new THREE.Mesh(new THREE.CylinderGeometry(0.003, 0.01, h * 0.5, 5), bark); b.position.y = h * rnd(0.55, 0.9); b.rotation.set(rnd(-1.1, 1.1), rnd(0, 6), rnd(-1.1, 1.1)); b.translateY(h * 0.2); g.add(b); } };
    for (const [x, z, h] of [[-0.95, -0.7, 0.55], [1.0, -0.6, 0.6], [-1.3, 0.2, 0.5], [1.3, 0.3, 0.45], [0.2, -1.4, 0.7]]) tree(x, z, h);
    // a gate of iron bars behind the opponent
    for (let i = -6; i <= 6; i++) box(i * 0.04, 0.12, -1.0, 0.006, 0.3, 0.006, std(0x1a1a1f, { metalness: 0.7 }));
    box(0, 0.27, -1.0, 0.52, 0.012, 0.012, std(0x1a1a1f, { metalness: 0.7 }));
    for (const s of [-1, 1]) box(s * 0.3, 0.15, -1.0, 0.07, 0.36, 0.07, grave);
    const moon = add(new THREE.Sprite(new THREE.SpriteMaterial({ map: glowTex("rgba(255,250,235,1)", "rgba(200,180,255,.35)"), fog: false, depthWrite: false }))); moon.position.set(1.4, 1.6, -5); moon.scale.set(2.2, 2.2, 1);
    for (const s of [-1, 1]) brazier(c, s * 0.48, -0.88, 0x9a7aff, "rgba(220,200,255,1)", "rgba(140,80,255,.6)");
  },
  // the Egyptian desert: pyramids, obelisks, a sandstone shrine and palms
  egypt(c) {
    const { add, box, sand } = c, stone = new THREE.MeshStandardMaterial({ map: stoneTex("#d0a868", 2), roughness: 0.95 });
    for (const [x, z, s] of [[-1.6, -3.4, 1.4], [0.6, -4.2, 2], [2.4, -3.2, 1.1]]) { const p = add(new THREE.Mesh(new THREE.ConeGeometry(s * 0.72, s, 4), stone)); p.position.set(x, s / 2 - 0.05, z); p.rotation.y = Math.PI / 4; }
    for (const s of [-1, 1]) {
      const o = add(new THREE.Mesh(new THREE.CylinderGeometry(0.02, 0.045, 0.62, 4), stone)); o.position.set(s * 0.46, 0.28, -0.9); o.rotation.y = Math.PI / 4;
      add(new THREE.Mesh(new THREE.ConeGeometry(0.03, 0.06, 4), std(0xffd060, { metalness: 0.8, roughness: 0.3 }))).position.set(s * 0.46, 0.62, -0.9);
    }
    // the shrine: a doorway of stone with a glowing eye above it
    box(0, 0.15, -1.15, 0.5, 0.36, 0.1, stone); box(0, 0.36, -1.15, 0.6, 0.06, 0.12, stone);
    box(0, 0.1, -1.095, 0.16, 0.24, 0.01, std(0x1a120a));
    const eye = add(new THREE.Mesh(new THREE.CircleGeometry(0.035, 24), glowMat(0xffd060))); eye.position.set(0, 0.3, -1.094);
    c.animated.push(t => { eye.material.color.setHSL(0.12, 1, 0.55 + Math.sin(t * 2) * 0.1); });
    for (const [x, z] of [[-1.0, -0.4], [1.05, -0.55], [-1.2, 0.4], [1.3, 0.35]]) palm(c, x, z, rnd(0.4, 0.6));
    rocks(c, sand, 30, 0.7, 1.6);
    for (const s of [-1, 1]) brazier(c, s * 0.62, -0.95, 0xffa040);
  },
  // the Kaiba stadium: a metal arena under spotlights, stands all around, towers and giant screens
  stadium(c) {
    const { add, box, animated } = c, steel = std(0x2a3346, { metalness: 0.7, roughness: 0.35 }), blue = glowMat(0x3aa0ff);
    for (let k = 0; k < 4; k++) { const r = 1.6 + k * 0.35, ring = add(new THREE.Mesh(new THREE.CylinderGeometry(r + 0.3, r, 0.18, 48, 1, true, Math.PI * 0.05, Math.PI * 1.9), std(k % 2 ? 0x1a2030 : 0x222a3c, { side: THREE.DoubleSide }))); ring.position.y = 0.06 + k * 0.17; ring.rotation.y = Math.PI / 2 + Math.PI * 0.05; }
    for (let k = 0; k < 4; k++) { const edge = add(new THREE.Mesh(new THREE.TorusGeometry(1.6 + k * 0.35, 0.006, 6, 96, Math.PI * 1.9), blue)); edge.rotation.set(Math.PI / 2, 0, Math.PI * 0.05 + Math.PI / 2); edge.position.y = 0.15 + k * 0.17; }
    // towers with lights, and spotlight beams sweeping over the field
    for (const [x, z] of [[-1.3, -1.3], [1.3, -1.3], [-1.6, 0.8], [1.6, 0.8]]) {
      box(x, 0.6, z, 0.07, 1.26, 0.07, steel); box(x, 1.24, z, 0.22, 0.1, 0.06, steel);
      add(new THREE.Mesh(new THREE.PlaneGeometry(0.2, 0.08), glowMat(0xeaf6ff))).position.set(x, 1.24, z + 0.031);
      const beam = add(new THREE.Mesh(new THREE.ConeGeometry(0.22, 1.5, 16, 1, true).translate(0, -0.75, 0), glowMat(0x9fd4ff, 0.07))); beam.position.set(x, 1.22, z);
      const s = rnd(0, 6); beam.lookAt(0, 0, 0); beam.rotateX(Math.PI / 2); animated.push(t => { beam.rotation.z = Math.sin(t * 0.5 + s) * 0.25; });
    }
    // the giant screen behind the opponent
    box(0, 0.62, -1.5, 1.1, 0.58, 0.04, steel);
    const scr = add(new THREE.Mesh(new THREE.PlaneGeometry(1.0, 0.5), new THREE.MeshBasicMaterial({ map: canvasTex(256, 128, (g, w, h) => { const gr = g.createLinearGradient(0, 0, w, h); gr.addColorStop(0, "#0a2a6a"); gr.addColorStop(1, "#1a6ad0"); g.fillStyle = gr; g.fillRect(0, 0, w, h); g.fillStyle = "#cfe8ff"; g.font = "800 40px Sora, sans-serif"; g.textAlign = "center"; g.fillText("DUEL!", w / 2, h / 2 + 14); }), fog: false })));
    scr.position.set(0, 0.62, -1.477); animated.push(t => { scr.material.color.setScalar(0.85 + Math.sin(t * 3) * 0.15); });
    for (const s of [-1, 1]) brazier(c, s * 0.62, -0.95, 0x5ab0ff, "rgba(220,240,255,1)", "rgba(60,150,255,.6)");
  },
  // the Duel Academy island: grass by the sea, palm trees, the academy building and a volcano
  academy(c) {
    const { add, box } = c;
    water(c, 0x2a8ac0, -0.09);
    add(new THREE.Mesh(new THREE.CylinderGeometry(2.4, 2.7, 0.08, 40), std(0x4f7a34))).position.y = -0.07;
    add(new THREE.Mesh(new THREE.CylinderGeometry(2.7, 2.9, 0.04, 40), std(0xe0c890))).position.y = -0.1;
    // the academy: a long building with a blue roof and a golden pyramid on top
    const wallM = std(0xf2ead8), roof = std(0x2a5aa8);
    box(0, 0.2, -2.0, 1.6, 0.46, 0.5, wallM); box(0, 0.45, -2.0, 1.66, 0.04, 0.56, roof);
    for (const s of [-1, 1]) { box(s * 0.95, 0.3, -1.9, 0.36, 0.66, 0.36, wallM); add(new THREE.Mesh(new THREE.ConeGeometry(0.26, 0.24, 4), roof)).position.set(s * 0.95, 0.75, -1.9); }
    const pyr = add(new THREE.Mesh(new THREE.ConeGeometry(0.42, 0.42, 4), std(0xffc440, { metalness: 0.7, roughness: 0.3 }))); pyr.position.set(0, 0.68, -2.0); pyr.rotation.y = Math.PI / 4;
    for (let i = -6; i <= 6; i++) add(new THREE.Mesh(new THREE.PlaneGeometry(0.06, 0.12), std(0x8ac8ff, { emissive: 0x2a5a8a }))).position.set(i * 0.11, 0.24, -1.745);
    // the volcano smoking far away
    const vol = add(new THREE.Mesh(new THREE.ConeGeometry(1.2, 1.1, 16, 1, true), std(0x5a4a3a, { flatShading: true }))); vol.position.set(-2.8, 0.45, -4.2);
    const smoke = add(new THREE.Sprite(new THREE.SpriteMaterial({ map: glowTex("rgba(230,230,230,.9)", "rgba(200,200,200,.3)"), depthWrite: false }))); smoke.position.set(-2.8, 1.2, -4.2); smoke.scale.set(0.8, 0.8, 1);
    c.animated.push(t => { smoke.position.y = 1.15 + Math.sin(t * 0.3) * 0.05; });
    for (const [x, z] of [[-0.95, -0.5], [1.0, -0.6], [-1.2, 0.3], [1.25, 0.35], [-0.6, -1.2], [0.7, -1.25]]) palm(c, x, z, rnd(0.45, 0.65));
    for (const s of [-1, 1]) brazier(c, s * 0.6, -0.92, 0xffb050);
  },
  // a neon city at night: towers with coloured windows, neon signs, and rain
  neon(c) {
    const { add, box, animated } = c;
    city(c, ["#ff4fd8", "#4fe3ff", "#b07bff", "#ffe16b"], 46, 1.6, 3.4, "#0c0814");
    const neon = [0xff3df0, 0x3de8ff, 0xffe14f, 0x9b6bff];
    for (let i = 0; i < 14; i++) {
      const a = rnd(-Math.PI * 0.95, -Math.PI * 0.05), d = rnd(1.4, 2.4), sign = add(new THREE.Mesh(new THREE.PlaneGeometry(rnd(0.12, 0.3), rnd(0.04, 0.08)), glowMat(neon[i % 4])));
      sign.position.set(Math.cos(a) * d * 1.3, rnd(0.2, 0.9), Math.sin(a) * d); sign.lookAt(0, sign.position.y, 0);
      const s = rnd(0, 6); animated.push(t => { sign.material.opacity = Math.sin(t * 7 + s) > -0.9 ? 1 : 0.3; sign.material.transparent = true; });
    }
    // street lamps around the platform
    const pole = std(0x2a2a33, { metalness: 0.6 });
    for (const [x, z] of [[-0.85, -0.7], [0.85, -0.7], [-0.95, 0.45], [0.95, 0.45]]) { box(x, 0.16, z, 0.012, 0.38, 0.012, pole); add(new THREE.Mesh(new THREE.SphereGeometry(0.02, 10, 8), glowMat(0xffe6c0))).position.set(x, 0.36, z); const l = add(new THREE.PointLight(0xff9ae8, 0.35, 1.2, 1.8)); l.position.set(x, 0.34, z); }
    // a highway ramp crossing behind
    box(0, 0.42, -1.6, 4, 0.04, 0.24, std(0x24242c)); for (const x of [-1.4, 0, 1.4]) box(x, 0.2, -1.6, 0.08, 0.42, 0.08, std(0x2c2c36));
    for (const s of [-1, 1]) add(new THREE.Mesh(new THREE.BoxGeometry(4, 0.01, 0.01), glowMat(s < 0 ? 0xff3df0 : 0x3de8ff))).position.set(0, 0.445, -1.6 + s * 0.12);
  },
  // cyberspace: a glowing grid, floating data cubes and towers of light
  cyber(c) {
    const { add, animated } = c, wire = new THREE.MeshBasicMaterial({ color: 0x2ef3ff, wireframe: true, transparent: true, opacity: 0.6, fog: false });
    for (let i = 0; i < 18; i++) {
      const a = rnd(-Math.PI * 0.98, -Math.PI * 0.02), d = rnd(1.5, 3.2), h = rnd(0.6, 2.2), w = rnd(0.15, 0.4);
      const t = add(new THREE.Mesh(new THREE.BoxGeometry(w, h, w), wire)); t.position.set(Math.cos(a) * d * 1.3, h / 2 - 0.03, Math.sin(a) * d);
      add(new THREE.Mesh(new THREE.BoxGeometry(w * 0.96, h * 0.99, w * 0.96), new THREE.MeshBasicMaterial({ color: 0x021a2a, transparent: true, opacity: 0.6 }))).position.copy(t.position);
    }
    const cubeM = [0x2ef3ff, 0xff4fd8, 0x7b6bff].map(col => new THREE.MeshBasicMaterial({ color: col, transparent: true, opacity: 0.75 }));
    for (let i = 0; i < 30; i++) {
      const s = rnd(0.02, 0.06), cube = add(new THREE.Mesh(new THREE.BoxGeometry(s, s, s), cubeM[i % 3])), a = rnd(0, Math.PI * 2), d = rnd(0.7, 1.6), y0 = rnd(0.15, 0.7), sp = rnd(0.3, 1);
      cube.position.set(Math.cos(a) * d * 1.2, y0, Math.sin(a) * d);
      animated.push(t => { cube.rotation.set(t * sp, t * sp * 0.7, 0); cube.position.y = y0 + Math.sin(t * sp + i) * 0.04; });
    }
    // a ring of light behind the opponent, like a data gate
    const gate = add(new THREE.Mesh(new THREE.TorusGeometry(0.32, 0.012, 8, 64), glowMat(0x2ef3ff))); gate.position.set(0, 0.36, -1.1);
    const gate2 = add(new THREE.Mesh(new THREE.TorusGeometry(0.26, 0.006, 8, 64), glowMat(0xff4fd8))); gate2.position.set(0, 0.36, -1.1);
    animated.push(t => { gate.rotation.z = t * 0.5; gate2.rotation.y = t * 0.8; });
  },
};
