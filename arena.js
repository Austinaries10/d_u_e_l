// The duel arena: an old stone courtyard in the ruins around the board, drawn in code (no downloads).
// The board is about 0.43 × 0.67 (metres, real card size) and sits at the centre on y = 0; the opponent's side is -z.
import * as THREE from "three";

// a seeded random, so the ruins look the same every duel
let seed = 7;
const rnd = (a = 0, b = 1) => { seed = (seed * 16807) % 2147483647; return a + (b - a) * (seed / 2147483647); };

function canvasTex(w, h, draw, repeat) {
  const c = document.createElement("canvas"); c.width = w; c.height = h; draw(c.getContext("2d"), w, h);
  const t = new THREE.CanvasTexture(c); t.colorSpace = THREE.SRGBColorSpace; t.anisotropy = 8;
  if (repeat) { t.wrapS = t.wrapT = THREE.RepeatWrapping; t.repeat.set(repeat, repeat); }
  return t;
}
// speckled stone, for walls and columns
const stoneTex = (base, repeat = 1) => canvasTex(256, 256, (g, w, h) => {
  g.fillStyle = base; g.fillRect(0, 0, w, h);
  for (let i = 0; i < 2600; i++) { g.fillStyle = `rgba(${rnd() < 0.5 ? "0,0,0" : "255,240,210"},${rnd(0.03, 0.12)})`; g.fillRect(rnd(0, w), rnd(0, h), rnd(1, 4), rnd(1, 4)); }
  g.strokeStyle = "rgba(40,25,10,.25)"; g.lineWidth = 1;
  for (let i = 0; i < 8; i++) { g.beginPath(); let x = rnd(0, w), y = rnd(0, h); g.moveTo(x, y); for (let k = 0; k < 5; k++) { x += rnd(-18, 18); y += rnd(-18, 18); g.lineTo(x, y); } g.stroke(); }
}, repeat);
// cracked flagstones for the courtyard floor
const floorTex = () => canvasTex(512, 512, (g, w, h) => {
  g.fillStyle = "#2a2622"; g.fillRect(0, 0, w, h);
  const rows = 8;
  for (let r = 0; r < rows; r++) {
    let x = -rnd(0, 40); const y = r * h / rows;
    while (x < w) {
      const sw = rnd(40, 90), l = rnd(22, 38);
      g.fillStyle = `hsl(${rnd(22, 34)},${rnd(8, 16)}%,${l}%)`;
      g.beginPath(); g.moveTo(x + 3 + rnd(-2, 2), y + 3); g.lineTo(x + sw - 3, y + 3 + rnd(-2, 2)); g.lineTo(x + sw - 3 + rnd(-2, 2), y + h / rows - 3); g.lineTo(x + 3, y + h / rows - 3 + rnd(-2, 2)); g.closePath(); g.fill();
      for (let i = 0; i < 60; i++) { g.fillStyle = `rgba(0,0,0,${rnd(0.04, 0.14)})`; g.fillRect(x + rnd(4, sw - 6), y + rnd(4, h / rows - 6), rnd(1, 5), rnd(1, 5)); }
      if (rnd() < 0.35) { g.strokeStyle = "rgba(20,14,8,.55)"; g.lineWidth = 1.2; g.beginPath(); let cx = x + rnd(6, sw - 6), cy = y + 4; g.moveTo(cx, cy); while (cy < y + h / rows - 6) { cx += rnd(-6, 6); cy += rnd(4, 9); g.lineTo(cx, cy); } g.stroke(); }
      if (rnd() < 0.18) { g.fillStyle = `rgba(70,110,50,${rnd(0.25, 0.5)})`; for (let i = 0; i < 14; i++) g.fillRect(x + rnd(0, sw), y + h / rows - rnd(2, 8), rnd(2, 5), rnd(1, 3)); }
      x += sw;
    }
  }
}, 7);
// the stone platform the board sits on, with a glowing ring of runes around its edge
const platformTex = () => canvasTex(1024, 1024, (g, w, h) => {
  const c = w / 2;
  g.fillStyle = "#3a342d"; g.fillRect(0, 0, w, h);
  for (let i = 0; i < 9000; i++) { g.fillStyle = `rgba(${rnd() < 0.5 ? "0,0,0" : "255,235,200"},${rnd(0.02, 0.08)})`; g.fillRect(rnd(0, w), rnd(0, h), rnd(1, 4), rnd(1, 4)); }
  g.strokeStyle = "rgba(15,10,5,.5)"; g.lineWidth = 2;
  for (let i = 1; i < 6; i++) { g.beginPath(); g.moveTo(0, i * h / 6); g.lineTo(w, i * h / 6); g.stroke(); g.beginPath(); g.moveTo(i * w / 6 + (i % 2) * 20, 0); g.lineTo(i * w / 6 + (i % 2) * 20, h); g.stroke(); }
  g.shadowColor = "#5ff2ff"; g.shadowBlur = 16; g.strokeStyle = "rgba(120,240,255,.6)";
  for (const [r, lw] of [[c - 30, 5], [c - 86, 3]]) { g.lineWidth = lw; g.beginPath(); g.arc(c, c, r, 0, 7); g.stroke(); }
  g.font = "34px serif"; g.fillStyle = "rgba(150,245,255,.65)"; g.textAlign = "center"; g.textBaseline = "middle";
  const runes = "ᚠᚢᚦᚨᚱᚲᚷᚹᚺᚾᛁᛃᛇᛈᛉᛊᛏᛒᛖᛗᛚᛜᛞᛟ";
  for (let i = 0; i < 48; i++) { const a = i / 48 * Math.PI * 2; g.save(); g.translate(c + Math.cos(a) * (c - 58), c + Math.sin(a) * (c - 58)); g.rotate(a + Math.PI / 2); g.fillText(runes[i % runes.length], 0, 0); g.restore(); }
  g.lineWidth = 2; g.strokeStyle = "rgba(120,240,255,.35)";
  for (let i = 0; i < 4; i++) { const a = i * Math.PI / 2 + Math.PI / 4; g.beginPath(); g.moveTo(c + Math.cos(a) * (c - 86), c + Math.sin(a) * (c - 86)); g.lineTo(c + Math.cos(a) * (c - 150), c + Math.sin(a) * (c - 150)); g.stroke(); }
});
const skyTex = () => canvasTex(16, 512, (g, w, h) => {
  const gr = g.createLinearGradient(0, 0, 0, h);
  gr.addColorStop(0, "#0b1530"); gr.addColorStop(0.3, "#2b2f5c"); gr.addColorStop(0.44, "#7a5a7e"); gr.addColorStop(0.495, "#e29a6a"); gr.addColorStop(0.515, "#3b3346"); gr.addColorStop(1, "#1d1a22");
  g.fillStyle = gr; g.fillRect(0, 0, w, h);
});
const glowTex = (inner, outer) => canvasTex(128, 128, (g, w, h) => {
  const gr = g.createRadialGradient(w / 2, h / 2, 0, w / 2, h / 2, w / 2); gr.addColorStop(0, inner); gr.addColorStop(0.35, outer); gr.addColorStop(1, "rgba(0,0,0,0)");
  g.fillStyle = gr; g.fillRect(0, 0, w, h);
});

export function buildArena() {
  seed = 7;
  const arena = new THREE.Group(), animated = [];
  const sand = new THREE.MeshStandardMaterial({ map: stoneTex("#b59a72"), roughness: 0.9 });
  const darkStone = new THREE.MeshStandardMaterial({ map: stoneTex("#6d6255", 2), roughness: 0.95 });

  // floor, with the board's platform a step above it
  const floor = new THREE.Mesh(new THREE.PlaneGeometry(9, 9).rotateX(-Math.PI / 2), new THREE.MeshStandardMaterial({ map: floorTex(), roughness: 0.95 }));
  floor.position.y = -0.03; arena.add(floor);
  const plat = new THREE.Mesh(new THREE.CylinderGeometry(0.56, 0.6, 0.028, 64), [new THREE.MeshStandardMaterial({ map: stoneTex("#4a4239", 4), roughness: 0.9 }), new THREE.MeshStandardMaterial({ map: platformTex(), roughness: 0.85 }), darkStone]);
  plat.position.y = -0.016; arena.add(plat);
  // the runes breathe: a glow layer just above the platform
  const runeGlow = new THREE.Mesh(new THREE.CircleGeometry(0.56, 64).rotateX(-Math.PI / 2), new THREE.MeshBasicMaterial({ map: plat.material[1].map, transparent: true, opacity: 0.5, blending: THREE.AdditiveBlending, depthWrite: false }));
  runeGlow.position.y = -0.0015; runeGlow.renderOrder = -2; arena.add(runeGlow);
  animated.push(t => { runeGlow.material.opacity = 0.16 + Math.sin(t * 1.3) * 0.07; });

  // a fluted column; broken ones have a jagged top
  function column(x, z, h, broken = false, r = 0.034) {
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
    const shaft = new THREE.Mesh(geo, sand); shaft.position.y = h / 2 + 0.02; g.add(shaft);
    const base = new THREE.Mesh(new THREE.BoxGeometry(r * 2.8, 0.02, r * 2.8), sand); base.position.y = 0.01; g.add(base);
    if (!broken) { const cap = new THREE.Mesh(new THREE.BoxGeometry(r * 2.8, 0.022, r * 2.8), sand); cap.position.y = h + 0.031; g.add(cap); }
    g.position.set(x, -0.03, z); g.rotation.y = rnd(0, 6); arena.add(g); return g;
  }
  const rock = (x, z, s) => { const m = new THREE.Mesh(new THREE.DodecahedronGeometry(s, 0), sand); m.position.set(x, -0.03 + s * 0.4, z); m.rotation.set(rnd(0, 3), rnd(0, 3), rnd(0, 3)); m.scale.y = rnd(0.5, 0.9); arena.add(m); };
  const block = (x, y, z, w, h, d, m = sand) => { const b = new THREE.Mesh(new THREE.BoxGeometry(w, h, d), m); b.position.set(x, y, z); arena.add(b); return b; };

  // behind the opponent: a wall with steps up to a stone arch, flanked by a pair of columns with a lintel and banners
  const back = -1.05;
  for (let i = 0; i < 6; i++) block(0, -0.03 + 0.012 + i * 0.024, back + 0.17 - i * 0.03, 0.42, 0.024, 0.06 + i * 0.0, darkStone);
  for (const s of [-1, 1]) {
    block(s * 0.72, 0.02, back, 0.95, 0.1, 0.07, darkStone);
    for (let i = 0; i < 9; i++) block(s * (0.3 + i * 0.1 + rnd(-0.01, 0.01)), 0.075 + (i % 3 === 2 ? -0.02 : 0), back, 0.095, 0.03, 0.065, sand);
  }
  // the arch
  const archR = 0.2, archY = 0.16;
  for (const s of [-1, 1]) block(s * archR, archY / 2 + 0.02, back - 0.06, 0.05, archY + 0.04, 0.05);
  for (let i = 0; i <= 8; i++) { const a = i / 8 * Math.PI; const b = block(Math.cos(a) * archR, archY + 0.04 + Math.sin(a) * archR, back - 0.06, 0.05, 0.035, 0.05); b.rotation.z = a - Math.PI / 2; }
  const portal = new THREE.Mesh(new THREE.CircleGeometry(archR - 0.02, 32, 0, Math.PI), new THREE.MeshBasicMaterial({ map: glowTex("rgba(160,240,255,.9)", "rgba(60,140,255,.35)"), transparent: true, blending: THREE.AdditiveBlending, depthWrite: false }));
  portal.position.set(0, archY + 0.04, back - 0.062); arena.add(portal);
  const portalLow = new THREE.Mesh(new THREE.PlaneGeometry((archR - 0.02) * 2, archY + 0.04), portal.material); portalLow.position.set(0, (archY + 0.04) / 2 + 0.0, back - 0.062); arena.add(portalLow);
  animated.push(t => { portal.material.opacity = 0.55 + Math.sin(t * 2.1) * 0.15; });
  // the gate columns and the lintel with purple banners
  for (const s of [-1, 1]) {
    column(s * 0.42, back + 0.26, 0.62, false, 0.042);
    block(s * 0.42, 0.62 + 0.035, back + 0.26, 0.16, 0.035, 0.1);
    const banner = new THREE.Mesh(new THREE.PlaneGeometry(0.08, 0.26, 1, 8), new THREE.MeshStandardMaterial({ color: 0x3d2a6b, roughness: 0.8, side: THREE.DoubleSide, emissive: 0x150a2a }));
    banner.geometry.translate(0, -0.1, 0); banner.position.set(s * 0.3, 0.64, back + 0.26); arena.add(banner);
    const bp = banner.geometry.attributes.position, by = Float32Array.from({ length: bp.count }, (_, i) => bp.getY(i));
    animated.push(t => { for (let i = 0; i < bp.count; i++) bp.setZ(i, Math.sin(t * 1.6 + by[i] * 22 + s) * 0.006 * -by[i] * 5); bp.needsUpdate = true; });
  }
  // the courtyard: broken columns, a fallen one and rubble
  for (const [x, z, h] of [[-0.66, -0.38, 0.26], [0.68, -0.34, 0.34], [-0.72, 0.3, 0.16], [0.7, 0.36, 0.2], [-1.0, -0.12, 0.42], [1.05, 0.05, 0.3]]) column(x, z, h, true);
  for (const [x, z, h] of [[-0.9, -0.62, 0.6], [0.92, -0.64, 0.6]]) column(x, z, h, false, 0.042);
  { const f = new THREE.Mesh(new THREE.CylinderGeometry(0.03, 0.033, 0.26, 18), sand); f.rotation.set(0, 0.5, Math.PI / 2); f.position.set(-0.72, -0.0, 0.0); arena.add(f); }
  for (let i = 0; i < 46; i++) { const a = rnd(0, Math.PI * 2), d = rnd(0.66, 1.5); rock(Math.cos(a) * d * 1.2, Math.sin(a) * d, rnd(0.008, 0.028)); }

  // two braziers by the gate, with flickering fire
  const fireTex = glowTex("rgba(255,240,180,1)", "rgba(255,120,30,.6)");
  for (const s of [-1, 1]) {
    const x = s * 0.6, z = back + 0.36;
    const bowl = new THREE.Mesh(new THREE.CylinderGeometry(0.035, 0.018, 0.03, 16, 1, true), new THREE.MeshStandardMaterial({ color: 0x6b4b2a, metalness: 0.6, roughness: 0.4, side: THREE.DoubleSide }));
    bowl.position.set(x, 0.1, z); arena.add(bowl);
    const stand = new THREE.Mesh(new THREE.CylinderGeometry(0.008, 0.016, 0.1, 8), bowl.material); stand.position.set(x, 0.035, z); arena.add(stand);
    const flame = new THREE.Sprite(new THREE.SpriteMaterial({ map: fireTex, blending: THREE.AdditiveBlending, depthWrite: false })); flame.position.set(x, 0.135, z); arena.add(flame);
    const light = new THREE.PointLight(0xff9a4a, 0.6, 1.4, 1.6); light.position.set(x, 0.18, z); arena.add(light);
    animated.push(t => { const f = 0.85 + Math.sin(t * 13 + s * 3) * 0.08 + Math.sin(t * 7.3 + s) * 0.07; flame.scale.set(0.09 * f, 0.12 * f, 1); light.intensity = 0.6 * f; });
  }

  // far away: hills and pine trees in the haze, and a castle on the hill
  const hill = new THREE.MeshStandardMaterial({ color: 0x2f3d2c, roughness: 1, flatShading: true });
  for (let i = 0; i < 9; i++) { const m = new THREE.Mesh(new THREE.SphereGeometry(rnd(1, 1.8), 10, 6, 0, Math.PI * 2, 0, Math.PI / 2), hill); m.scale.y = rnd(0.22, 0.4); m.position.set(rnd(-4, 4), -0.05, rnd(-4.4, -2.6)); arena.add(m); }
  const pine = new THREE.MeshStandardMaterial({ color: 0x1f3324, roughness: 1, flatShading: true });
  for (let i = 0; i < 40; i++) {
    const a = rnd(-Math.PI * 0.95, -Math.PI * 0.05), d = rnd(1.5, 3), h = rnd(0.25, 0.55);
    const tree = new THREE.Mesh(new THREE.ConeGeometry(h * 0.28, h, 6), pine); tree.position.set(Math.cos(a) * d * 1.3, h / 2 - 0.02, Math.sin(a) * d); arena.add(tree);
  }
  for (const [x, w, h] of [[0, 0.28, 0.36], [-0.2, 0.09, 0.5], [0.2, 0.09, 0.5]]) {
    const tw = block(x, h / 2 + 0.35, -3.1, w, h, w, darkStone);
    const roof = new THREE.Mesh(new THREE.ConeGeometry(w * 0.75, w * 1.1, 4), new THREE.MeshStandardMaterial({ color: 0x3b2f4a })); roof.position.set(x, h + 0.35 + w * 0.55, -3.1); roof.rotation.y = Math.PI / 4; arena.add(roof); tw.userData.far = true;
  }

  // floating dust and embers drifting up through the light
  const N = 160, pts = new Float32Array(N * 3), speed = new Float32Array(N);
  for (let i = 0; i < N; i++) { pts.set([rnd(-1.1, 1.1), rnd(0, 0.6), rnd(-1.1, 0.8)], i * 3); speed[i] = rnd(0.008, 0.03); }
  const dustGeo = new THREE.BufferGeometry(); dustGeo.setAttribute("position", new THREE.BufferAttribute(pts, 3));
  const dust = new THREE.Points(dustGeo, new THREE.PointsMaterial({ size: 0.012, map: glowTex("rgba(255,230,180,1)", "rgba(255,170,90,.4)"), transparent: true, blending: THREE.AdditiveBlending, depthWrite: false, opacity: 0.8 }));
  arena.add(dust);
  let last = 0;
  animated.push(t => { const dt = Math.min(0.05, t - last); last = t; for (let i = 0; i < N; i++) { let y = pts[i * 3 + 1] + speed[i] * dt; if (y > 0.6) y = 0; pts[i * 3 + 1] = y; pts[i * 3] += Math.sin(t + i) * 0.0004; } dustGeo.attributes.position.needsUpdate = true; });

  // the evening sky all around, horizon at eye level
  const sky = new THREE.Mesh(new THREE.SphereGeometry(8, 32, 16), new THREE.MeshBasicMaterial({ map: skyTex(), side: THREE.BackSide, fog: false, depthWrite: false }));
  sky.renderOrder = -10; arena.add(sky);
  // warm evening light from behind the gate, cool fill from the front
  const rim = new THREE.DirectionalLight(0xffc48a, 0.7); rim.position.set(-0.4, 0.6, -1); arena.add(rim);
  return { group: arena, fog: new THREE.Fog(0x3b3346, 2.2, 6.5), update: t => { for (const f of animated) f(t); } };
}
