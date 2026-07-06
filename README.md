# Redeyed Sentinel for Joomla 4/5

A self-contained Joomla 4/5 CAPTCHA plugin that adds **Redeyed Sentinel** — a
self-hosted CAPTCHA + IP-reputation service — to any Joomla form that uses the
site's CAPTCHA (login, registration, contact, comments, and more).

The plugin is **free to install** and stays **inert** (verification fails open,
nothing is rendered) until you provide both a **Site Key** and a **Secret Key**.
No developer API key is required — the flow is reCAPTCHA/Turnstile-style.

## How it works

- **Render** — loads `{base_url}/sentinel.js` (async) and outputs
  `<div class="sentinel-captcha" data-sitekey="{site_key}"></div>`. The Sentinel
  widget injects a hidden `sentinel-token` input into the form. Optional widget
  customisation params (Widget Type, Theme, Colour Scheme, Difficulty, Width)
  add the matching `data-*` attributes to that div, but only when they are set.
- **Verify** — on submit, the plugin POSTs to `{base_url}/sentinel/siteverify`
  with the JSON body `{"secret":"<secret_key>","response":"<sentinel-token>"}`
  (plus an optional `"remoteip"` of the client). The Secret Key authenticates the
  call — there is **no** `X-Api-Key` header. The challenge passes only when the
  response's top-level `success` is `true` (the response also carries `outcome`
  and `score`).
- **Inert by default** — if the Secret Key is empty, the widget is not rendered
  and verification fails open, so an unconfigured site is never blocked.

## Install

1. **Zip the plugin folder.** Compress the contents of this `redeyed-joomla`
   folder into a single `.zip` (the `redeyed.xml` manifest must be at the root of
   the archive).
2. In Joomla admin, go to **System → Install → Extensions**.
3. Drag the zip onto the **Upload Package File** area (or browse to it) and
   install.
4. Go to **System → Manage → Plugins**, search for **"CAPTCHA - Redeyed
   Sentinel"**, open it, and set status to **Enabled**.

## Configure

In the plugin's settings:

- **Site Key** — public key from **Redeyed Lab → Sentinel → Sites**. Renders the
  widget in the browser.
- **Secret Key** — from the same **Redeyed Lab → Sentinel → Sites** screen (shown
  once). Used server-side only to authenticate the verify call at
  `/sentinel/siteverify`; never exposed to the browser. No developer API key is
  needed.
- **Base URL** — defaults to `https://redeyed.com`.

### Optional widget customisation

All five are optional and blank by default; each is rendered as a `data-*`
attribute on the widget `<div>` **only when non-empty**, so leaving them blank
keeps Sentinel's fully adaptive behaviour and stays backward-compatible.

- **Widget Type** (`data-widget`) — force a specific widget: `behavioral`,
  `checkbox`, `press_hold`, `image_pick`, … Leave on the adaptive default to let
  Sentinel choose.
- **Theme** (`data-theme`) — `auto`, `light`, or `dark`. Blank inherits the
  widget default.
- **Colour Scheme** (`data-scheme`) — a named colour scheme for the widget.
- **Difficulty** (`data-difficulty`) — minimum challenge strength: `easy`,
  `medium`, `hard`, `max` (or `1`–`6`). This only **raises** difficulty above
  Sentinel's adaptive baseline — it never lowers it. Blank uses the adaptive
  default.
- **Width** (`data-width`) — fixed widget width, e.g. `300px` or `100%`. Blank
  uses the widget default.

Save. Then make Sentinel the active CAPTCHA:

1. Go to **System → Global Configuration → Site**.
2. Set **Default Captcha** to **CAPTCHA - Redeyed Sentinel**.
3. Save.

Individual extensions (e.g. Contacts, User registration) can also choose a CAPTCHA
in their own options if they don't follow the global default.

The plugin is free, but it needs a Site Key and a Secret Key to do anything.

## Requirements

- Joomla 4.x or 5.x
- PHP 8.1+

## Changelog

### 1.0.4

- Add widget **Width** option (`data-width`) — a fixed widget width (e.g.
  `300px` or `100%`) rendered on the widget `<div>` only when set.

### 1.0.3

- Send proxy-aware `remoteip` on verification (CF-Connecting-IP /
  X-Forwarded-For / X-Real-IP / REMOTE_ADDR) so it matches the IP that solved
  the challenge. Fixes `remoteip` reporting the proxy address when Joomla sits
  behind Cloudflare or a reverse proxy.

### 1.0.2

- Add optional widget customisation params: **Widget Type** (`data-widget`),
  **Theme** (`data-theme`), **Colour Scheme** (`data-scheme`), and **Difficulty**
  (`data-difficulty`). Each renders as a `data-*` attribute on the widget `<div>`
  only when set, so behaviour is unchanged when they are blank. Difficulty only
  raises challenge strength above the adaptive baseline; it never lowers it.

### 1.0.1

- Fix CAPTCHA verification: use the per-site **Secret Key** (reCAPTCHA/Turnstile
  style) instead of a developer API key. Server-side verify now POSTs to
  `{base_url}/sentinel/siteverify` with `{"secret","response","remoteip?"}` and
  no `X-Api-Key` header; a `success: true` response passes. Renamed the `api_key`
  setting to `secret_key`.

### 1.0.0

- Initial release.

## License

MIT — see [LICENSE](LICENSE).
