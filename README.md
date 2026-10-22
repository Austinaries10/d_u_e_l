# AR Hunt (SmartEyes)

A Pokémon Go-style AR collecting game for the phone browser. Things from where you are (the food, animals and landmarks of your state) appear around you in the camera. Tap to throw a sepak takraw ball and collect them. Stand at a real landmark to make it appear and collect its chop.

Live: https://smarteyes.cck8.my

## How it plays
- **Location decides what appears.** GPS gives the country and Malaysian state (reverse-geocoded with BigDataCloud's free client API, with rough state boxes as a fallback). Items from your state count most, then Malaysia-wide items, then everyday things found everywhere (cats, dogs, butterflies, treasure). The area can also be chosen by hand
- **Two modes**: on phones with WebXR (Android Chrome with ARCore) objects stand on the real floor; elsewhere they appear in the camera view and you turn the phone to look around, with a radar and an arrow pointing to them
- **Catching**: tap an object to throw a sepak takraw ball. Rarer objects escape more often; legendary ones move. Every item has a local name and a fact. Points and levels are saved on the phone
- **Landmark chops**: real places with GPS coordinates. Within the radius, the landmark appears; catching it stamps a dated chop in the passport, which also shows how far away the others are
- **My objects**: import your own `.glb` models; they join the hunt or can be placed in front of you. Stored on the phone (IndexedDB)
- **Photo** (camera mode) shares or downloads the AR picture

## What appears where
| Area | Items |
|---|---|
| Everywhere | cat, dog, butterfly, gold coin, crystal, treasure chest |
| Malaysia | durian, rambutan, ketupat, teh tarik, nasi lemak, bunga raya, Rajah Brooke's birdwing |
| Kuala Lumpur | Petronas Twin Towers, Menara KL |
| Penang | char kway teow, asam laksa, trishaw |
| Melaka | Christ Church, Melaka trishaw |
| Sarawak | Sarawak laksa, kek lapis, Kenyalang hornbill, orangutan, rafflesia |
| Sabah | Mount Kinabalu, orangutan, green turtle, rafflesia |
| Kelantan | wau bulan, nasi kerabu, rafflesia |
| Terengganu | green turtle |
| Perak | Ipoh white coffee, rafflesia |
| Selangor | satay |
| Thailand, Singapore, Indonesia | their own sets |

**Landmark chops**: Petronas Twin Towers, Menara KL, Thean Hou Temple, Kek Lok Si, Khoo Kongsi, Cheng Hoon Teng, Christ Church Melaka, A Famosa, Tua Pek Kong Kuching, Great Cat of Kuching, Kinabalu Park, Sam Poh Tong. Coordinates are approximate (each has a 400 m–2 km radius) and live in the `LANDMARKS` list in `index.html`, so they are easy to correct or extend.

## Bag, repeats and unlocks
- Normal items keep appearing; every catch adds one to the **Bag** (e.g. Durian × 7). The Collection records each kind once
- Items can have rules in `ITEM_LIST` (last column): `needs` makes an item appear only after owning enough of others, `limit` caps how many one person can own. Examples: **Musang King** needs 5 Durian; the **Golden Cat** needs 10 Cats and is one of a kind

## Partner shops
Shops and restaurants can appear in the game with their own branded 3D sign (or their own `.glb` model) and a reward.
- Edit **`partners.json`** in GitHub: copy the example, fill in name, address, `lat`/`lon` (right-click the shop in Google Maps), `radius` in metres, `brandColor`, `logoText`, `reward`, optional `glb` path and `validUntil`, then set `"active": true`. It goes live with the next deploy
- Players within the radius see the sign; catching it gives a voucher with a code (one per shop per day), kept in the Bag. Staff tap **Mark as used** to redeem it
- **Partner demo** (in Nearby): stand in a shop and create a test spot to show the owner. Test spots stay on that phone only
- Vouchers are checked on the honour system for now (stored on the player's phone). A server-side check is needed before running paid campaigns

## Nearby and the radar
**Nearby** lists landmarks and partner shops by distance with an arrow pointing the way (using the phone's compass when available). Places within 1.5 km show as diamonds on the radar rim, and when you arrive the object appears in its real direction.

## Adding things
- **New item**: add a model function to `MODELS` and a row to `ITEM_LIST` with where it appears (`"*"`, a country code, or state codes such as `"PNG,MLK"`)
- **New landmark**: add a row to `LANDMARKS` with its latitude, longitude, radius in metres and model

All 3D models are built in code with Three.js (PBR materials, environment lighting, soft shadows).

## Deploy
Every push to `main` uploads the site to the hosting through `.github/workflows/deploy.yml` (FTPS, using the `FTP_SERVER`, `FTP_USERNAME` and `FTP_PASSWORD` repository secrets). Only changed files are sent; files on the server that the repo never uploaded are left alone.

The camera and GPS only work over `https://`.

`server/` holds the PHP setup from the earlier AI features (Claude Haiku). It is unused by the game right now but kept for future AI features such as quizzes; its `.htaccess` blocks web access to it.
