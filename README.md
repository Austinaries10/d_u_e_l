# SmartEyes (AirHand)

A camera app you control with your fingers: show a number of fingers to press the button with that number. Runs in the phone browser using MediaPipe hand tracking.

Live: https://smarteyes.cck8.my

## Controls
- **5 fingers**: open the menu
- **1–5**: press the button with that number (hold until the ring fills)
- **Fist**: back / close
- **Notepad**: 1 finger writes, 2 fingers moves, 3 undo, 4 clear, 5 ink color

## Deploy
Every push to `main` uploads the site to the hosting through `.github/workflows/deploy.yml`.
It needs these repository secrets (Settings → Secrets and variables → Actions):

| Secret | Example |
|---|---|
| `FTP_SERVER` | `ftp.cck8.my` |
| `FTP_USERNAME` | `deploy@smarteyes.cck8.my` |
| `FTP_PASSWORD` | the FTP account password |
| `FTP_SERVER_DIR` | optional, defaults to `./` |

Create a dedicated FTP account in cPanel whose directory is the `smarteyes.cck8.my` document root, so the deploy can only touch this site.

The camera only works over `https://`.
