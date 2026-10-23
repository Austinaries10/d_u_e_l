# AR Duel (SmartEyes)

A Yu-Gi-Oh! style card duel in AR for the phone browser: the duel board is placed on a real table (or shown in a 3D preview) and played with virtual decks of real cards, against the characters of the series, the computer, or a friend online.

Live: https://smarteyes.cck8.my

## Playing
The home screen is a small world: **gates** for each Yu-Gi-Oh! series, and the **Shop** (Grandpa Solomon's Kame Game), **Duel Arena** (online), **Decks** and **Training** (practice against the computer). Tap your avatar to change your name and picture.
- **Gates**: Duel Monsters and GX are open; 5D's and ZEXAL show "opening soon" (their decks need Synchro and Xyz summoning, which come with the rules engine). Every gate is open from the start, so players can begin with any series
- **Characters arrive over time**: each gate starts with one character (Joey Wheeler, Syrus Truesdale). Others appear after hidden conditions are met (wins against certain characters and total wins at the gate), e.g. Téa after beating Joey, Weevil after 4 wins including Tristan, Kaiba after Bakura. A NEW badge marks a newcomer. The conditions are the `when` functions in `GATES` in `index.html`; players are never told them
- Duel Monsters: Joey, Téa, Tristan, Weevil (insects), Rex Raptor (dinosaurs), Mako Tsunami (sea), Mai Valentine (Harpies), Bandit Keith (machines), Bakura, Seto Kaiba, Pegasus (Toons), Marik. GX: Syrus, Chumley, Alexis, Bastion, Chazz, Dr. Crowler, Zane, Aster. Each has their own deck (`CHAR_DECKS`) and lines
- **Rewards** for each win: coins and one random card from that character's deck; their ace card (e.g. Joey's Red-Eyes Black Dragon, Kaiba's Blue-Eyes White Dragon) drops 1 time in 5, and for sure by the 5th win without it
- Character pictures come from each character's Yugipedia page, fetched once by `api/ygo.php?action=face` and cached. To use your own picture, put `Name_Of_Character.jpg` (or .png/.webp, named like the Yugipedia page) in `server/data/faces/` on the hosting
- **Training**: your deck against any character you have met, or against one of your own decks. No rewards
- **Duel Arena**: one player creates a room and shares the 5-letter code, the other joins. Both phones post their moves to `api/room.php` and poll for the other's once a second; rooms are files in `server/cache/rooms` and expire after a day. Reloading the page rebuilds the duel from the room's moves. The winner gets 100 coins, the loser 30
- **The computer** plays known spells when they help, Normal Summons its best monster (tributing when worth it), sets its traps, attacks only when it wins the battle, and answers your attacks with its traps
- **Board**: drag to turn the 3D view, pinch or scroll to zoom. In AR, twist two fingers to turn the board on the table. Holograms float above their own zone (higher for higher levels) and never get wider than a zone; your own step aside while you choose an attack target, and **Hide holograms** turns them off. Tapping a hologram works like tapping its card

## Rules the game applies
- One Normal Summon or Set per turn; level 5–6 monsters need 1 tribute, level 7+ need 2, the Egyptian gods 3. No attacks on the first turn of the duel
- Battles follow the standard ATK/DEF rules. When you are attacked and have a face-down trap, the game asks whether to activate it
- These effects happen by themselves: Pot of Greed, Raigeki, Dark Hole, Fissure, Hinotama, Sparks, Dian Keto the Cure Master, Monster Reborn (brings back the strongest monster in either graveyard), Harpie's Feather Duster, Heavy Storm, and the traps Mirror Force, Magic Cylinder, Negate Attack, Waboku and Sakuretsu Armor. Other cards are played by hand: activate them, then use Life points or Send to graveyard (`SPELLS` and `TRAPS` in `index.html`)

## Collecting
- **Starter deck**: a new player gets one of the real starter decks (Yugi, Kaiba, Joey or Pegasus) at random, as their first deck
- **Packs**: 3 free packs every day (reset at midnight, phone time), and more in the **Shop** for 100 coins each. Coins come from gate and online duels; a new player starts with 500
- **Real packs**: the Shop sells about 140 real booster sets (`PACKS` in `api/ygo.php`). Each card comes at its real rarity in that set. A pack has 9 cards: 7 Commons, a Rare (or Super Rare in newer sets) and a foil slot. Older sets (before 2008) have 8 Commons and one Rare-or-better. All-foil sets (Battles of Legend and similar) have 5 cards. Foil odds (`FOIL_ODDS` in `index.html`): Ultra Rare about 1 in 6, Secret Rare 1 in 12, Quarter Century and Ultimate 1 in 24, Ghost 1 in 144, Starlight and Platinum Secret 1 in 576, otherwise Super Rare
- Cards, decks and coins are kept on the phone (localStorage). Clearing the browser's site data resets them, and they can be changed by someone who knows how. Accounts on the server are needed before coins can be sold for real money

## Decks
- Up to 10 decks: create, rename, copy, delete
- A deck uses only cards you own (up to 3 copies, and no more than you own) and needs 20–60 cards to duel. You pick your deck before each duel
- **Search** your own cards, or all 14,000+ cards in the database. The words are matched in card names and in card text (monsters, spells and traps); name matches come first, and text matches are marked "found in card text". Filter by kind, attribute, type (Dragon, Spellcaster, Quick-Play…), level and archetype. Sort A–Z, by card type (monsters, spells, traps), ATK, DEF or level

## Board
- **Board**: real card-size zones (5 monster and 5 spell/trap per player, deck pile that shrinks as you draw, graveyard). Cards show their real images; a summoned monster's artwork rises above its zone as a glowing hologram
- **Scan real cards**: Claude Haiku (`api/card.php`) reads a card held up to the camera and it is matched to the database for its artwork
- **Own models**: attach a `.glb` model to any card name and that card shows it

## Game master (GM)
`gm.html` (not linked from the game) is the GM page. Its password is `'gm_password'` in `server/config.php` on the hosting (see `server/config.example.php`). Settings are saved in `server/data/gm.json`, which is blocked from the web and never touched by deploys. Players get changes the next time they open the game or the Shop.
- **Forbidden & Limited list**: load the latest official TCG list from YGOPRODeck with one button, then add, change or remove cards. Forbidden cards can't go in a deck; Limited allows 1 copy, Semi-Limited 2. A deck that breaks the list can't be used to duel until it is fixed; My decks shows why and has a **Remove banned copies** button. New players' starter decks follow the list. Characters' decks are not affected
- **Packs on sale**: take any booster pack off sale (or put it back) at any time
- **Limited pack events**: a pack sold only between a start and end time, with a name, description, price in coins and an optional limit per player. The pack is either a real set (any set in the database, with its real rarities) or the GM's own card list with a rarity for each card. Switch an event off or press **End now** to stop sales at once; after its end time it disappears from the Shop. The server refuses to hand out the cards of a pack that is not on sale

## Card data
`api/ygo.php` fetches card data, set lists and images from the YGOPRODeck API and caches them in `server/cache` (blocked from the web, not in git, not touched by deploys), so each image is downloaded only once.

## Deploy
Every push to `main` uploads the site to the hosting through `.github/workflows/deploy.yml` (FTPS, using the `FTP_SERVER`, `FTP_USERNAME` and `FTP_PASSWORD` repository secrets). Only changed files are sent.

The camera only works over `https://`. `server/` holds the PHP setup for Claude Haiku (the API key lives in `server/config.php` on the hosting only); its `.htaccess` blocks web access to it.
