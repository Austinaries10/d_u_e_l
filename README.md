# AR Duel (SmartEyes)

A Yu-Gi-Oh! style card duel in AR for the phone browser: the duel board is placed on a real table (or shown in a 3D preview) and played with virtual decks of real cards, against the characters of the series, the computer, or a friend online.

Live: https://smarteyes.cck8.my

## Accounts
Players log in with a duelist name and password (`api/account.php`). Their whole save (cards, decks, coins, free packs, character progress, avatar) is stored on the server in `server/data/users/` and copied to the browser, so they can carry on from any phone. Changes are saved a moment after they happen; if the save changed somewhere else (another phone, or the game master), the game reloads the newer save. A new account starts from whatever was on that phone, plus a starter deck.

## Duel rules engine
Duels run on EDOPro's rules engine (ocgcore, built for the browser as `ocgcore-wasm`, in `engine/ocgcore/`) inside a Web Worker (`engine/worker.js`). It knows the effects of practically every card, with chains, timing, costs, conditions, targets, and Fusion, Ritual, Synchro, Xyz, Pendulum and Link summons. Card scripts come from Project Ignis' CardScripts and card data and texts from their BabelCDB, fetched and cached by `api/engine.php`.
- The page plays the engine's events as animations (activations shown big in the middle, summons, attacks, damage) and asks the player whenever the engine needs a choice: actions on a card (tap it), which zone (glowing zones), cards to pick (a card picker that also accepts taps on the board), chains, yes/no, options, positions, declarations
- The computer opponent answers the same questions with simple heuristics
- Online duels run the same engine on both phones with the same seed; only the answers to each player's questions travel through the room
- A duel against the computer is saved as its seed plus every answer, and rebuilt by replaying them after a reload
- Because the engine scripts are AGPL-3.0, the whole game is under the AGPL-3.0 (`LICENSE`): anyone who plays it must be able to get the source code, linked from the home screen

## Playing
The home screen is a small world: **gates** for each Yu-Gi-Oh! series, and the **Shop** (Grandpa Solomon's Kame Game), **Duel Arena** (online), **Decks** and **Training** (practice against the computer). Tap your avatar to change your name and picture.
- **Gates**: Duel Monsters, GX, 5D's, ZEXAL, ARC-V and VRAINS, all open from the start, so players can begin with any series
- **Characters arrive over time**: each gate starts with one character (Joey Wheeler, Syrus Truesdale). Others appear after hidden conditions are met (wins against certain characters and total wins at the gate), e.g. Téa after beating Joey, Weevil after 4 wins including Tristan, Kaiba after Bakura. A NEW badge marks a newcomer. The conditions are the `when` functions in `GATES` in `index.html`; players are never told them
- 59 characters, each with their own deck (`CHAR_DECKS`, with an Extra Deck where they use one), ace card and lines:
  - Duel Monsters: Joey, Téa, Tristan, Weevil, Rex Raptor, Mako Tsunami, Mai Valentine, Paradox Brothers, Bandit Keith, Espa Roba, Bakura, Bonz, Seto Kaiba, Lumis and Umbra, Pegasus, Ishizu, Odion, Marik, Yami Yugi
  - GX: Syrus, Chumley, Alexis, Bastion, Chazz, Tyranno Hassleberry, Atticus, Dr. Crowler, Zane, Axel, Aster, Jesse, Yubel, Jaden
  - 5D's: Leo, Luna, Trudge, Crow, Akiza, Kalin, Jack Atlas, Yusei
  - ZEXAL: Yuma, Shark, Trey, Quattro, Kite, Vector
  - ARC-V: Zuzu, Sora, Declan, Shay, Yuto, Yuya
  - VRAINS: Go Onizuka, Blue Angel, Ghost Girl, Soulburner, Revolver, Playmaker
- **Rewards** for each win: coins and one random card from that character's deck; their ace card (e.g. Joey's Red-Eyes Black Dragon, Kaiba's Blue-Eyes White Dragon) drops 1 time in 5, and for sure by the 5th win without it
- Character pictures come from each character's Yugipedia page, fetched once by `api/ygo.php?action=face` and cached. To use your own picture, put `Name_Of_Character.jpg` (or .png/.webp, named like the Yugipedia page) in `server/data/faces/` on the hosting
- **Training**: your deck against any character you have met, or against one of your own decks. No rewards
- **Duel Arena**: one player creates a room and shares the 5-letter code, the other joins. Both phones post their moves to `api/room.php` and poll for the other's once a second; rooms are files in `server/cache/rooms` and expire after a day. Reloading the page rebuilds the duel from the room's moves. The winner gets 100 coins, the loser 30
- **The computer** plays known spells when they help, Normal Summons its best monster (tributing when worth it), sets its traps, attacks only when it wins the battle, and answers your attacks with its traps
- **Board**: drag to turn the 3D view, pinch or scroll to zoom. In AR, twist two fingers to turn the board on the table. Holograms float above their own zone (higher for higher levels) and never get wider than a zone; your own step aside while you choose an attack target, and **Hide holograms** turns them off. Tapping a hologram works like tapping its card

## Collecting
- **Starter deck**: a new player gets a random starter deck without a main character's name (Starter Deck 2006, the 5D's starters, Duelist Toolbox, Dawn of the Xyz, Xyz Symphony, Space-Time Showdown, Link Strike or Codebreaker; `STARTERS` in `api/ygo.php`). Character decks such as Pegasus' are won through the gates
- **Packs**: the GM chooses which packs the **Shop** sells, each with its own price and optional start and end time (until then, the first eight booster sets at 100 coins). 3 free packs every day (refilled at midnight; packs won as rewards are kept). Coins come from gate and online duels, quests and login rewards; a new player starts with 500
- **Rewards** (the Rewards spot in town): a daily login reward on a 7-day cycle (coins and free packs, bigger on day 7) with bonuses for 7, 14 and 30 days in a row; daily quests (duel once, win at a gate, win 3 duels, open 2 packs, and a free pack for finishing them all); story rewards at each gate for beating 3, then 6, then every duelist there. The coin **top-up** is marked as coming soon
- **Real packs**: each card comes at its real rarity in that set. A pack has 9 cards: 7 Commons, a Rare (or Super Rare in newer sets) and a foil slot. Older sets (before 2008) have 8 Commons and one Rare-or-better. All-foil sets (Battles of Legend and similar) have 5 cards. Foil odds (`FOIL_ODDS` in `index.html`): Ultra Rare about 1 in 6, Secret Rare 1 in 12, Quarter Century and Ultimate 1 in 24, Ghost 1 in 144, Starlight and Platinum Secret 1 in 576, otherwise Super Rare
- Cards, decks and coins belong to the player's account. The game still works them out on the phone (packs are opened there), so a determined player could tamper with them; moving pack opening and rewards to the server would be needed before selling coins for real money

## Decks
- Up to 10 decks: create, rename, copy, delete
- A deck uses only cards you own (up to 3 copies, and no more than you own): a Main Deck of 20–60 cards and an Extra Deck of up to 15 (Fusion, Synchro, Xyz, Link). You pick your deck before each duel
- **Search** your own cards, or all 14,000+ cards in the database. The words are matched in card names and in card text (monsters, spells and traps); name matches come first, and text matches are marked "found in card text". Filter by kind, attribute, type (Dragon, Spellcaster, Quick-Play…), level and archetype. Sort A–Z, by card type (monsters, spells, traps), ATK, DEF or level

## Board
- **Board**: real card-size zones with the modern layout (5 Main Monster Zones, 2 shared Extra Monster Zones, 5 Spell/Trap Zones and a Field Zone per player; Deck, Extra Deck, GY and banished piles). Cards show their real images; a monster's artwork floats above its zone as a glowing hologram. Drag to turn the 3D view, pinch to zoom; in AR, twist two fingers to turn the board
- **Scan real cards**: Claude Haiku (`api/card.php`) reads a card held up to the camera and matches it to the database (saved to recent cards; the engine deals the hand in a duel)
- **Own models**: attach a `.glb` model to any card name and that card shows it

## Game master (GM)
`gm.html` (not linked from the game) is the GM page. Its password is `'gm_password'` in `server/config.php` on the hosting (see `server/config.example.php`). Settings are saved in `server/data/gm.json`, which is blocked from the web and never touched by deploys. Players get changes the next time they open the game or the Shop.
- **Forbidden & Limited list**: load the latest official TCG list from YGOPRODeck with one button, then add, change or remove cards. Forbidden cards can't go in a deck; Limited allows 1 copy, Semi-Limited 2. A deck that breaks the list can't be used to duel until it is fixed; My decks shows why and has a **Remove banned copies** button. New players' starter decks follow the list. Characters' decks are not affected
- **Shop**: choose which packs are sold (any set in the database), with a price in coins and an optional start and end time for each; switch one off or press **End now** at any time. The server refuses to open a pack that isn't on sale
- **Players**: search players; change coins and free packs, give or remove cards at any rarity, edit or reset character progress, reset a password, suspend or restore an account. The player's game picks up the change the next time it opens or syncs
- **Limited pack events**: a pack sold only between a start and end time, with a name, description, price in coins and an optional limit per player. The pack is either a real set (any set in the database, with its real rarities) or the GM's own card list with a rarity for each card. Switch an event off or press **End now** to stop sales at once; after its end time it disappears from the Shop. The server refuses to hand out the cards of a pack that is not on sale

## Card data
`api/ygo.php` fetches card data, set lists and images from the YGOPRODeck API and caches them in `server/cache` (blocked from the web, not in git, not touched by deploys), so each image is downloaded only once.

## Deploy
Every push to `main` uploads the site to the hosting through `.github/workflows/deploy.yml` (FTPS, using the `FTP_SERVER`, `FTP_USERNAME` and `FTP_PASSWORD` repository secrets). Only changed files are sent.

The camera only works over `https://`. `server/` holds the PHP setup for Claude Haiku (the API key lives in `server/config.php` on the hosting only); its `.htaccess` blocks web access to it.
