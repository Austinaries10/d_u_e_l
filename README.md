# SmartEyes (AirHand)

A camera app you control with your fingers: show a number of fingers to press the button with that number. Runs in the phone browser using MediaPipe hand tracking.

Live: https://smarteyes.cck8.my

## Controls
- **5 fingers**: open the menu
- **1–5**: press the button with that number (hold still until the ring fills)
- **Fist**: back / close
- **After every press, lower your hand** out of view. The app waits, so you can read before the next press.

## Apps
- **Notepad**: 1 finger writes, 2 fingers moves, 3 undo, 4 clear, 5 turns the writing into text (AI)
- **Camera**: 1 photo (2 second timer), 2 start/stop video, 3 gallery. Kept on the phone (browser storage); "Save to phone" in the gallery copies a file to Photos/Downloads
- **Lens**: point at something, 1 asks the AI what it is
- **Translate**: point at text, 1 English, 2 Malay, 3 Chinese
- Music and Navigate are placeholders for now

## AI setup (Lens, Translate, handwriting)
`api/lens.php` calls Claude Haiku (`claude-haiku-5-5`) with the Anthropic PHP SDK. The key never goes to the browser.

1. Get an API key at console.anthropic.com.
2. In cPanel File Manager, open the site folder, go to `server/`, create `config.php` with:
   ```php
   <?php
   return ['anthropic_api_key' => 'sk-ant-...'];
   ```
   (`server/` is blocked from the web and `config.php` is never in GitHub.)
3. The hosting needs PHP 8.1 or newer (cPanel → MultiPHP Manager).

Each request is limited to 30 per IP address per 10 minutes.

## Deploy
Every push to `main` uploads the site to the hosting through `.github/workflows/deploy.yml`.

The PHP libraries (`server/vendor`, about 4,000 files) are **not** uploaded by the deploy, because FTP is slow with many small files. Upload them once by hand:
1. Build the zip: `cd server && composer install --no-dev --optimize-autoloader && zip -qr ../server-vendor.zip vendor`
2. In cPanel File Manager, open the site's `server/` folder, upload `server-vendor.zip`, right-click → Extract, then delete the zip.

Repeat only when `server/composer.json` changes.
It needs these repository secrets (Settings → Secrets and variables → Actions):

| Secret | Example |
|---|---|
| `FTP_SERVER` | `ftp.cck8.my` |
| `FTP_USERNAME` | `deploy@smarteyes.cck8.my` |
| `FTP_PASSWORD` | the FTP account password |
| `FTP_SERVER_DIR` | optional, defaults to `./` |

Create a dedicated FTP account in cPanel whose directory is the `smarteyes.cck8.my` document root, so the deploy can only touch this site.

The camera only works over `https://`.
