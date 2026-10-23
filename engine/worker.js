// Runs the duel rules engine (EDOPro's ocgcore) off the main thread.
// The page sends {t:"start"} with the decks and seed, then {t:"resp"} with each answer to a prompt.
// (TYPE is left out of board queries: this build of the wrapper misreads it; card types come from the card data.)
// After every run of the engine the worker posts {t:"batch"}: the messages, a snapshot of the board,
// and the details of any cards seen for the first time.
import createCore, { OcgProcessResult, OcgDuelMode, OcgLocation, OcgPosition, OcgQueryFlags, OcgMessageType } from "./ocgcore/index.js";

const API = new URL("../api/engine.php", import.meta.url).href;
const cards = new Map(), sent = new Set(), scripts = new Map();
let lib = null, duel = null;

// Workers may read files synchronously, which is what the engine needs while it runs a card script
function getSync(url) {
  const x = new XMLHttpRequest(); x.open("GET", url, false); x.send();
  return x.status === 200 ? x.responseText : null;
}
async function loadCards(codes) {
  const need = [...new Set(codes)].filter(c => c && !cards.has(c));
  for (let i = 0; i < need.length; i += 300) {
    const r = await fetch(`${API}?action=cards&codes=${need.slice(i, i + 300).join(",")}`), j = await r.json();
    for (const [k, v] of Object.entries(j.cards || {})) cards.set(+k, v);
  }
}
function cardData(code) {
  if (!cards.has(code)) { try { for (const [k, v] of Object.entries(JSON.parse(getSync(`${API}?action=cards&codes=${code}`)).cards || {})) cards.set(+k, v); } catch {} }
  const c = cards.get(code); if (!c) return null;
  return { code: c.code, alias: c.alias, setcodes: c.setcodes, type: c.type, level: c.level, attribute: c.attribute, race: BigInt(c.race), attack: c.attack, defense: c.defense, lscale: c.lscale, rscale: c.rscale, link_marker: c.link_marker };
}
function script(name) {
  if (!scripts.has(name)) { const s = getSync(`${API}?action=script&name=${encodeURIComponent(name)}`); scripts.set(name, s || null); }
  return scripts.get(name);
}

const FLAGS = OcgQueryFlags.CODE | OcgQueryFlags.POSITION | OcgQueryFlags.LEVEL | OcgQueryFlags.RANK | OcgQueryFlags.LINK
  | OcgQueryFlags.ATTACK | OcgQueryFlags.DEFENSE | OcgQueryFlags.OVERLAY_CARD | OcgQueryFlags.COUNTERS | OcgQueryFlags.IS_PUBLIC | OcgQueryFlags.ATTRIBUTE | OcgQueryFlags.RACE;
const LOCS = { hand: OcgLocation.HAND, mzone: OcgLocation.MZONE, szone: OcgLocation.SZONE, grave: OcgLocation.GRAVE, removed: OcgLocation.REMOVED, extra: OcgLocation.EXTRA };
// The whole board, as the engine sees it right now
function snapshot() {
  const players = [0, 1].map(p => {
    const out = { deck: lib.duelQueryCount(duel, p, OcgLocation.DECK) };
    for (const [k, loc] of Object.entries(LOCS)) out[k] = lib.duelQueryLocation(duel, { flags: FLAGS, controller: p, location: loc }).map(c => c && c.code !== undefined ? {
      code: c.code, position: c.position, attack: c.attack, defense: c.defense, level: c.level || c.rank || c.link?.rating || 0, overlays: c.overlayCards?.length || 0, public: !!c.isPublic,
    } : null);
    return out;
  });
  return players;
}
// Details of cards the page hasn't been told about yet (names, texts, effect strings)
function newCards(field, msgs) {
  const codes = new Set();
  for (const p of field) for (const k of Object.keys(LOCS)) for (const c of p[k]) if (c) codes.add(c.code);
  const walk = o => { if (!o || typeof o !== "object") return; for (const [k, v] of Object.entries(o)) { if ((k === "code" || k === "card") && typeof v === "number") codes.add(v); else if (typeof v === "object") walk(v); } };
  msgs.forEach(walk);
  const out = {};
  for (const c of codes) if (!sent.has(c)) { if (!cards.has(c)) cardData(c); const d = cards.get(c); if (d) { out[c] = d; sent.add(c); } }
  return out;
}
function run() {
  for (;;) {
    const status = lib.duelProcess(duel), msgs = lib.duelGetMessage(duel), field = snapshot();
    const ended = status === OcgProcessResult.END || msgs.some(m => m.type === OcgMessageType.WIN);
    postMessage({ t: "batch", msgs, field, cards: newCards(field, msgs), waiting: status === OcgProcessResult.WAITING && !ended, ended });
    if (ended || status === OcgProcessResult.WAITING) return;
  }
}

onmessage = async ({ data }) => {
  try {
    if (data.t === "start") {
      lib ??= await createCore({ sync: true });
      if (duel) { lib.destroyDuel(duel); duel = null; }
      // decks[p] = { main: [codes], extra: [codes] } for engine players 0 and 1 (player 0 goes first)
      await loadCards(data.decks.flatMap(d => [...d.main, ...d.extra]));
      duel = lib.createDuel({
        flags: OcgDuelMode.MODE_MR5, seed: data.seed.map(BigInt),
        team1: { startingLP: 8000, startingDrawCount: 5, drawCountPerTurn: 1 }, team2: { startingLP: 8000, startingDrawCount: 5, drawCountPerTurn: 1 },
        cardReader: cardData, scriptReader: script, errorHandler: (type, text) => postMessage({ t: "log", type, text }),
      });
      lib.loadScript(duel, "constant.lua", script("constant.lua"));
      lib.loadScript(duel, "utility.lua", script("utility.lua"));
      data.decks.forEach((d, p) => {
        for (const code of d.main) lib.duelNewCard(duel, { team: p, duelist: 0, code, controller: p, location: OcgLocation.DECK, sequence: 0, position: OcgPosition.FACEDOWN_DEFENSE });
        for (const code of d.extra) lib.duelNewCard(duel, { team: p, duelist: 0, code, controller: p, location: OcgLocation.EXTRA, sequence: 0, position: OcgPosition.FACEDOWN_DEFENSE });
      });
      lib.startDuel(duel);
      run();
    } else if (data.t === "resp" && duel) {
      lib.duelSetResponse(duel, data.r);
      run();
    }
  } catch (e) { postMessage({ t: "error", text: String(e?.message || e) }); }
};
