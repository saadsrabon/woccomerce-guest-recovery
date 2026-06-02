# GitHub Releases — Update Guide

Repository: **https://github.com/saadsrabon/woccomerce-guest-recovery**

WordPress sites check this repo’s **latest GitHub Release** for updates (configured under **WooCommerce → GCRM Settings**).

## One-time: push code to GitHub

From the folder that contains `guest-customer-recovery-marketing-suite/`:

```bash
cd "D:/New folder/Details"
git init
git add guest-customer-recovery-marketing-suite
git commit -m "Initial release: Guest Customer Recovery & Marketing Suite 1.0.0"
git branch -M main
git remote add origin https://github.com/saadsrabon/woccomerce-guest-recovery.git
git push -u origin main
```

## Each new version

1. **Bump version** in two places:
   - `guest-customer-recovery-marketing-suite.php` header: `Version: 1.0.1`
   - `define( 'GCRM_VERSION', '1.0.1' );`

2. **Build the zip** (folder name inside the zip must be `guest-customer-recovery-marketing-suite`):

   ```bash
   cd guest-customer-recovery-marketing-suite/..
   zip -r guest-customer-recovery-marketing-suite-1.0.1.zip guest-customer-recovery-marketing-suite -x "*.git*"
   ```

   On Windows (PowerShell), compress the `guest-customer-recovery-marketing-suite` folder so the zip root is that folder name.

3. **Create a GitHub Release**
   - Go to: https://github.com/saadsrabon/woccomerce-guest-recovery/releases/new
   - Tag: `v1.0.1` (must match version; `v` prefix is OK)
   - Title: `1.0.1`
   - Description: changelog for admins
   - **Attach** `guest-customer-recovery-marketing-suite-1.0.1.zip` as a release asset

   Important: attach a `.zip` with the correct plugin folder name. Do not rely on “Source code (zip)” alone — that zip uses the repo name, not `guest-customer-recovery-marketing-suite`, and WordPress updates may fail.

4. **On client sites**
   - Updates are detected automatically (cached ~12 hours), or click **Check for updates** in GCRM Settings.
   - Install via **Update now** or **Plugins → Updates**.

## Verify API (optional)

```text
https://api.github.com/repos/saadsrabon/woccomerce-guest-recovery/releases/latest
```

Should return `tag_name` and an asset with `browser_download_url` pointing to your `.zip`.
