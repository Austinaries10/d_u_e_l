# AR Hunt (SmartEyes)

A Pokémon Go-style AR collecting game for the phone browser. Things from where you are (the food, animals and landmarks of your state) appear around you in the camera. Tap to throw a sepak takraw ball and collect them. Stand at a real landmark to make it appear and collect its chop.

Live: https://smarteyes.cck8.my

## How it plays
- **Location decides what appears.** GPS gives the country and Malaysian state (reverse-geocoded with BigDataCloud's free client API, with rough state boxes as a fallback). Items from your state count most, then Malaysia-wide items, then everyday things found everywhere (cats, dogs, butterflies, treasure). The area can also be chosen by hand
- **Two modes**: on phones with WebXR (Android Chrome with ARCore) objects stand on the real floor; elsewhere they appear in the camera view and you turn the phone to look around, with a radar and an arrow pointing to them
- **Catching**: flick the sepak takraw ball at an object. The flick's speed sets how far it flies, its angle sets left/right, and a curved swipe adds spin; the ball flies under gravity and can miss. A hit near the centre ("Excellent!") gives a better catch chance than a graze ("Nice!"). Rarer objects escape more often; legendary ones move. Every item has a local name and a fact. Points and levels are saved on the phone
- **Not crowded**: at most 2 wild things at once (3 at a famous spot). A new one appears every 45–90 s (15–30 s at a famous spot) or after walking 150 m. With GPS and a compass each is anchored to a real spot 4–8 m away; it disappears after 3 minutes or when you walk away
- **Famous spots**: places known for an item make it far more likely there, e.g. char kway teow at Gurney Drive, asam laksa at Air Itam Market, orangutans at Semenggoh and Sepilok, turtles at Rantau Abang. See `HOTSPOTS` in `index.html`
- **Landmark chops**: real places anchored to their real position. Far away nothing shows. Within the visible radius (about 500 m) a light beacon with the name and distance stands in the real direction. Within 60 m the 3D landmark stands at its real spot (it gets bigger as you walk closer and stays put when you turn) and can be caught, which stamps a dated chop in the passport. Anchoring uses GPS plus the phone's compass; without a compass the landmark is placed in front of you once you are within 60 m
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

**Landmark chops**: Petronas Twin Towers, Menara KL, Thean Hou Temple, Kek Lok Si, Khoo Kongsi, Cheng Hoon Teng, Christ Church Melaka, A Famosa, Tua Pek Kong Kuching, Great Cat of Kuching, Timpohon Gate (Kinabalu Park), Sam Poh Tong. Coordinates come from OpenStreetMap and live in the `LANDMARKS` list in `index.html` (`CATCH_R` sets the 60 m catch distance). Phone GPS is usually accurate to 5–20 m and compasses to 10–20°, so the anchored position can be a little off, more so between tall buildings.

## Bag, repeats and unlocks
- Normal items keep appearing; every catch adds one to the **Bag** (e.g. Durian × 7). The Collection records each kind once
- Items can have rules in `ITEM_LIST` (last column): `needs` makes an item appear only after owning enough of others, `limit` caps how many one person can own. Examples: **Musang King** needs 5 Durian; the **Golden Cat** needs 10 Cats and is one of a kind

## Partner shops
Shops and restaurants can appear in the game with their own branded 3D sign (or their own `.glb` model) and a reward.
- Edit **`partners.json`** in GitHub: copy the example, fill in name, address, `lat`/`lon` (right-click the shop in Google Maps), `radius` in metres, `brandColor`, `logoText`, `reward`, optional `glb` path and `validUntil`, then set `"active": true`. It goes live with the next deploy
- `radius` is how far away players see a beacon pointing to the shop (default 300 m); `catchRadius` is how close they must be for the sign to stand at the shop and be caught (default 40 m). Catching it gives a voucher with a code (one per shop per day), kept in the Bag. Staff tap **Mark as used** to redeem it
- **Partner demo** (in Nearby): stand in a shop and create a test spot to show the owner. Test spots stay on that phone only
- Vouchers are checked on the honour system for now (stored on the player's phone). A server-side check is needed before running paid campaigns

## Nearby and the radar
**Nearby** lists landmarks and partner shops by distance with an arrow pointing the way (using the phone's compass when available). Places within 1.5 km show as diamonds on the radar rim, and when you arrive the object appears in its real direction.

## Adding things
- **New item**: add a model function to `MODELS` and a row to `ITEM_LIST` with where it appears (`"*"`, a country code, or state codes such as `"PNG,MLK"`)
- **New famous spot**: add a row to `HOTSPOTS` with name, latitude, longitude, radius in metres and the item ids it is known for
- **New landmark**: add a row to `LANDMARKS` with its exact latitude and longitude (right-click the spot in Google Maps, or look it up on OpenStreetMap), visible radius in metres, model and height

All 3D models are built in code with Three.js (PBR materials, environment lighting, soft shadows).

## Deploy
Every push to `main` uploads the site to the hosting through `.github/workflows/deploy.yml` (FTPS, using the `FTP_SERVER`, `FTP_USERNAME` and `FTP_PASSWORD` repository secrets). Only changed files are sent; files on the server that the repo never uploaded are left alone.

The camera and GPS only work over `https://`.

`server/` holds the PHP setup from the earlier AI features (Claude Haiku). It is unused by the game right now but kept for future AI features such as quizzes; its `.htaccess` blocks web access to it.
