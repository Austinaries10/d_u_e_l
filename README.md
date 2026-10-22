# AR Duel (SmartEyes)

A Yu-Gi-Oh! style card duel in AR for the phone browser: the duel board is placed on a real table (or shown in a 3D preview) and played with virtual decks of real cards.

Live: https://smarteyes.cck8.my

## Collecting
- **Starter deck**: a new player gets one of the real starter decks (Yugi, Kaiba, Joey or Pegasus) at random, as their first deck
- **Packs**: 3 free packs every day (reset at midnight, phone time), and more in the **Shop** for 100 coins each. Each finished duel pays 100 coins; a new player starts with 500
- **Real packs**: the Shop sells about 140 real booster sets (`PACKS` in `api/ygo.php`). Each card comes at its real rarity in that set. A pack has 9 cards: 7 Commons, a Rare (or Super Rare in newer sets) and a foil slot. Older sets (before 2008) have 8 Commons and one Rare-or-better. All-foil sets (Battles of Legend and similar) have 5 cards. Foil odds (`FOIL_ODDS` in `index.html`): Ultra Rare about 1 in 6, Secret Rare 1 in 12, Quarter Century and Ultimate 1 in 24, Ghost 1 in 144, Starlight and Platinum Secret 1 in 576, otherwise Super Rare
- Cards, decks and coins are kept on the phone (localStorage). Clearing the browser's site data resets them, and they can be changed by someone who knows how. Accounts on the server are needed before coins can be sold for real money

## Decks
- Up to 10 decks: create, rename, copy, delete
- A deck uses only cards you own (up to 3 copies, and no more than you own) and needs 20–60 cards to duel. Each player picks a deck on the home screen
- **Search** your own cards, or all 14,000+ cards in the database, by name or card text. Filter by kind, attribute, type (Dragon, Spellcaster, Quick-Play…), level and archetype. Sort A–Z, by card type (monsters, spells, traps), ATK, DEF or level

## Duel
- New duel shuffles both decks and deals 5 cards each; each turn the next player draws one (running out of cards loses). A pass screen hides the hand until the next player taps
- **Board**: real card-size zones (5 monster and 5 spell/trap per player, deck pile that shrinks as you draw, graveyard). Cards show their real images; a summoned monster's artwork rises above its zone as a glowing hologram
- **Battles** follow the standard ATK/DEF rules; direct attacks, positions, flipping, graveyard and life point changes for card effects are included
- **Scan real cards**: Claude Haiku (`api/card.php`) reads a card held up to the camera and it is matched to the database for its artwork
- **Own models**: attach a `.glb` model to any card name and that card shows it

## Card data
`api/ygo.php` fetches card data, set lists and images from the YGOPRODeck API and caches them in `server/cache` (blocked from the web, not in git, not touched by deploys), so each image is downloaded only once.

## Deploy
Every push to `main` uploads the site to the hosting through `.github/workflows/deploy.yml` (FTPS, using the `FTP_SERVER`, `FTP_USERNAME` and `FTP_PASSWORD` repository secrets). Only changed files are sent.

The camera only works over `https://`. `server/` holds the PHP setup for Claude Haiku (the API key lives in `server/config.php` on the hosting only); its `.htaccess` blocks web access to it.
