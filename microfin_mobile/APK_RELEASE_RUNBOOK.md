# APK Release Runbook (No Local Flutter Needed)

This project now has cloud APK build automation in:

- `.github/workflows/build-generic-apk.yml`

The workflow builds from:

- `microfin_mobile/microfin_mobile`

And replaces the live download file:

- `microfin_mobile/microfin_app.apk`

## 1) Let Railway redeploy server fix first

Wait for Railway to finish deploying commit `bb8f546` (install attribution collation patch).

## 2) Build and replace APK from GitHub Actions

1. Open GitHub Actions for this repository.
2. Run workflow `Build Generic APK`.
3. Optional input: set `app_name` (default is `Bank App`).
4. Wait for success.

What happens automatically:

- Flutter release APK is built in GitHub runner.
- Result is copied to `microfin_mobile/microfin_app.apk`.
- A bot commit is pushed if the APK changed.

## 3) If branch protection blocks bot push

If the workflow cannot push directly:

1. Download workflow artifact `microfin_app-apk`.
2. Replace `microfin_mobile/microfin_app.apk` with that artifact.
3. Commit and push manually.

## 4) Verify live APK update

After push/redeploy:

1. Download the APK from your live link.
2. Confirm file timestamp/hash changed from old artifact.
3. Install test path:
   - Uninstall old app from device.
   - Install new APK.
   - Confirm app label and landing flow are the updated build.

## 5) Quick rollback

If needed, revert the APK-only commit and redeploy.
