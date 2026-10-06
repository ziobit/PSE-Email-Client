# Open PSE email files from Windows

PSE now registers `.pse` as an installed web app file type. Double-clicking a PSE file can open the actual file in PSE, where you can read it, reply, reply to all, forward its embedded attachments, or edit a copy. No Windows executable or local helper is required.

## Install the updated web app

1. Deploy the updated `index.php` at your existing PSE URL. The manifest, service worker, cat images and ICO download are served by that file; the separate `assets` folder is optional for deployment.
2. Open the **HTTPS** URL in current desktop **Microsoft Edge or Google Chrome** on Windows. Sign in and configure the email account normally.
3. Click **Install** in PSE, or the browser's install-app button/menu. In Edge the menu may show **Apps → Install this site as an app**; wording varies with browser release.
4. If PSE was installed before this update and does not appear in Windows' list for `.pse`, close its windows, uninstall that installed app from `edge://apps` or `chrome://apps`, and install it again from the same updated URL. Keep browser/site data if the uninstall dialog offers that choice.

The browser must install PSE as an app for operating-system file handling. A desktop shortcut to an ordinary website is insufficient. Installation belongs to a particular browser profile and a particular PSE URL.

## Set the default for all `.pse` files

1. Create a test file using PSE: open an email, choose **Export → Portable email (.PSE)**, and save it to your computer. For a draft, choose **Download .PSE** in the composer.
2. In File Explorer, right-click the file and choose **Open with → Choose another app**. Select **PSE Email** (or your configured application title), then choose **Always** / **Always use this app**.
3. Alternatively open **Windows Settings → Apps → Default apps**, search for `.pse`, and select the installed PSE app.
4. Approve the browser's request to let PSE open the file. Remember the permission if the browser offers that option.
5. Double-click another `.pse` file to confirm it opens in the installed PSE window.

Choose the **installed PSE application** in this list. Choosing the generic Chrome or Edge browser does not pass the local file to the web app's file handler. File associations must be selected through Windows; PHP on the server cannot silently change them.

## Open files in the existing app window

PSE asks supported desktop Chrome and Edge installations to focus an already-open PSE window and deliver the file there without reloading it. Selecting several `.pse` files together sends them to one app launch. If several PSE windows are open, the browser chooses the most recently used window. The existing window must belong to the same installed app URL and browser profile.

The browser may still create a window when none is open, when its installed manifest is outdated, or when launch reuse is unsupported. A window launched to open a `.pse` file goes straight to that file and skips the initial inbox synchronization and automatic mailbox refresh. Opening PSE normally still starts the mailbox as before. You can request a mailbox refresh yourself when you need current mail.

After updating PSE, open the installed app once, close its windows, and try opening a file with a PSE window already open. Browsers can retain an older installed manifest. If opening the file still creates another window, reinstall the app from the same updated URL using step 4 above, keeping browser/site data. Reinstallation may ask for file-opening permission again. A fresh file window still uses the faster file-opening path even when window reuse is unavailable.

## Give every `.pse` file the black cat icon

The manifest declares the new black cat as the `.pse` document icon. The same cat is now the default PSE application icon. An existing custom uploaded application icon is preserved. To also replace that custom app icon, download `index.php?pwa=cat-icon&size=256`, upload it in PSE's application-icon settings, then reinstall the PWA if Windows still displays its previous icon.

A multi-size Windows ICO is supplied as `assets/pse-black-cat.ico`. You can also download it directly from your PSE URL by appending `?pwa=cat-ico`, for example:

```text
https://your-domain.example/mail/index.php?pwa=cat-ico
```

If Windows or your browser keeps using a generic document icon, save the ICO to a permanent local path such as `C:\PSE\pse-black-cat.ico`. First select the **installed PSE app** as the `.pse` default using the steps above. This optional **PowerShell** snippet asks Windows for that effective handler and changes its document icon for the current user. The selected app and its launch command remain in place:

```powershell
$catIconPath = 'C:\PSE\pse-black-cat.ico'
if (!(Test-Path -LiteralPath $catIconPath)) {
  throw 'Save the cat ICO to this path first.'
}
if (!('PseFileIconTools' -as [type])) {
  Add-Type @'
using System;
using System.Text;
using System.Runtime.InteropServices;
public static class PseFileIconTools {
  [DllImport("shlwapi.dll", CharSet = CharSet.Unicode)]
  private static extern uint AssocQueryStringW(
    uint flags, uint value, string association, string extra,
    StringBuilder output, ref uint length
  );
  public static string CurrentProgId() {
    uint length = 0;
    AssocQueryStringW(0, 20, ".pse", null, null, ref length);
    if (length == 0) throw new InvalidOperationException("Select PSE as the .pse default first.");
    StringBuilder output = new StringBuilder((int)length);
    if (AssocQueryStringW(0, 20, ".pse", null, output, ref length) != 0)
      throw new InvalidOperationException("Cannot resolve the current .pse application.");
    return output.ToString();
  }
  [DllImport("shell32.dll")]
  public static extern void SHChangeNotify(uint eventId, uint flags, IntPtr item1, IntPtr item2);
}
'@
}
$pseProgId = [PseFileIconTools]::CurrentProgId()
if ($pseProgId -notmatch '^[A-Za-z0-9][A-Za-z0-9._-]{0,254}$' -or
    $pseProgId -match '^(ChromeHTML|MSEdgeHTM|htmlfile)([._-].*)?$') {
  throw 'Select the installed PSE app as the .pse default, then run this again.'
}
$catIconKey = "HKCU:\Software\Classes\$pseProgId\DefaultIcon"
New-Item -Path $catIconKey -Force | Out-Null
Set-Item -LiteralPath $catIconKey -Value ('"{0}",0' -f $catIconPath)
[PseFileIconTools]::SHChangeNotify(0x08000000, 0, [IntPtr]::Zero, [IntPtr]::Zero)
Write-Host "Updated document icon: $catIconKey"
```

Keep the ICO at that path. If Explorer retains an old cached icon, sign out of Windows and sign in again. The snippet prints the exact ProgID icon key it changed. To restore the browser-managed icon, reinstall the PSE app; alternatively restore that key's previous default value if you saved it before running the snippet. Browser updates or app reinstallation may replace the manual icon setting.

## How files behave in PSE

- **Open .PSE file** in the left sidebar also works in a normal browser tab and supplies a fallback when OS file handling is unavailable.
- Old `PSE/1` draft files remain compatible. Local files have their server draft IDs cleared, so editing a copy cannot overwrite an unrelated saved draft.
- Received-message exports preserve From, To, Cc, Bcc, Reply-To, subject, date, body and embedded attachment bytes. Forward retains the attachment bytes; Reply uses Reply-To when present.
- Export fails if an attachment cannot be downloaded. Server cache links are never substituted for embedded attachments. External images are blocked; available inline email images are included among the attachments.
- Opening a local file reads it in the browser. The original file is unchanged. Editing, saving a draft or sending can upload content to the configured PSE server/account through the existing workflows.
- If the application password is required, the launch handle is retained locally for up to 15 minutes through the sign-in reload when browser storage supports it. Otherwise the sign-in page tells you to double-click the file again after signing in.
- Each file is limited to 24 MB including JSON/base64 encoding, with a combined 15 MB attachment limit. One window accepts up to 20 files and 48 MB total.
- Reply and Forward use the currently selected account and still require a working server/account connection. The service worker does not provide an offline copy of the app or mailbox.
- Windows file-type registration and icons depend on desktop browser/OS support and policy. Mobile browsers and Firefox may require the manual file picker. Renaming an `.eml` or arbitrary JSON file to `.pse` does not convert it into a PSE email.

## Primary documentation

- [Microsoft Edge: Handle files in a PWA](https://learn.microsoft.com/en-us/microsoft-edge/progressive-web-apps/how-to/handle-files)
- [Chrome: Let installed web applications be file handlers](https://developer.chrome.com/docs/capabilities/web-apis/file-handling)
- [Chrome: Launch Handler API](https://developer.chrome.com/docs/web-platform/launch-handler)
- [Microsoft: Change default apps in Windows](https://support.microsoft.com/en-gb/windows/apps/change-default-apps-in-windows)
- [Microsoft: Assign a custom icon to a file type](https://learn.microsoft.com/en-us/windows/win32/shell/how-to-assign-a-custom-icon-to-a-file-type)
- [Microsoft: Query the effective file association](https://learn.microsoft.com/en-us/windows/win32/api/shlwapi/nf-shlwapi-assocquerystringw)
- [Microsoft: ASSOCSTR_PROGID and current default-program settings](https://learn.microsoft.com/en-us/windows/win32/api/shlwapi/ne-shlwapi-assocstr)
- [Microsoft: ProgID DefaultIcon registration](https://learn.microsoft.com/en-us/windows/win32/shell/fa-progids)

Documentation checked October 6, 2026. Installation/default-app labels can vary by Windows and browser version.

## Developer verification

The production app remains one PHP file. The DOM/security test uses `jsdom` as a test-only dependency:

```bash
npm install --no-save jsdom
node tests/local-pse-files.cjs
```

These checks exercise malformed records, HTML/script/attribute sanitization, portable attachment validation, legacy draft compatibility, Reply-To and exact attachment bytes during Forward. Windows double-click registration and Explorer icon appearance require a manual Windows/Chrome-or-Edge test.
