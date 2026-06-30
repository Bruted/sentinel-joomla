# Redeyed Sentinel for Joomla 4/5

A self-contained Joomla 4/5 CAPTCHA plugin that adds **Redeyed Sentinel** — a
self-hosted CAPTCHA + IP-reputation service — to any Joomla form that uses the
site's CAPTCHA (login, registration, contact, comments, and more).

The plugin is **free to install** and stays **inert** (verification fails open,
nothing is rendered) until you provide both a **Site Key** and an **API Key**.

## How it works

- **Render** — loads `{base_url}/sentinel.js` (async) and outputs
  `<div class="sentinel-captcha" data-sitekey="{site_key}"></div>`. The Sentinel
  widget injects a hidden `sentinel-token` input into the form.
- **Verify** — on submit, the plugin POSTs to `{base_url}/api/v1/verify` with the
  header `X-Api-Key: {api_key}` and JSON body
  `{"site_key":"...","token":"<sentinel-token>"}`. The challenge passes only when
  the response's `data.success` (or top-level `success`) is `true`.
- **Inert by default** — if either key is empty, the widget is not rendered and
  verification fails open, so an unconfigured site is never blocked.

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

- **Site Key** — public key from **Redeyed Lab → Developer → Sentinel Sites**.
- **API Key** — secret key from **Redeyed Lab → Developer → API Keys** (used
  server-side only; never exposed to the browser).
- **Base URL** — defaults to `https://redeyed.com`.

Save. Then make Sentinel the active CAPTCHA:

1. Go to **System → Global Configuration → Site**.
2. Set **Default Captcha** to **CAPTCHA - Redeyed Sentinel**.
3. Save.

Individual extensions (e.g. Contacts, User registration) can also choose a CAPTCHA
in their own options if they don't follow the global default.

The plugin is free, but it needs a Site Key and an API Key to do anything.

## Requirements

- Joomla 4.x or 5.x
- PHP 8.1+

## License

MIT — see [LICENSE](LICENSE).
