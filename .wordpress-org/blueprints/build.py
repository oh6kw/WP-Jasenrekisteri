#!/usr/bin/env python3
"""Rakentaa blueprint.json-tiedoston (WordPress.org Live Preview / WordPress Playground).
Aja: python3 .wordpress-org/blueprints/build.py  → .wordpress-org/blueprints/blueprint.json
SVN:ssä tiedosto kuuluu polkuun assets/blueprints/blueprint.json."""
import json, os
d = os.path.dirname(os.path.abspath(__file__))
read = lambda n: open(os.path.join(d, n), encoding='utf-8').read()
bp = {
    "$schema": "https://playground.wordpress.net/blueprint-schema.json",
    "meta": {
        "title": "OH6KW Jäsenrekisteri – esikatselu",
        "description": "Jäsenrekisteri ja jäsenmaksulaskutus keksityillä esimerkkijäsenillä ja yhdellä laskulla.",
        "author": "oh6kw",
    },
    "landingPage": "/wp-admin/admin.php?page=jasenrekisteri",
    "preferredVersions": {"php": "8.3", "wp": "latest"},
    "steps": [
        {"step": "login", "username": "admin", "password": "password"},
        {"step": "setSiteLanguage", "language": "fi"},
        {"step": "installPlugin", "pluginData": {"resource": "wordpress.org/plugins", "slug": "oh6kw-jasenrekisteri"}, "options": {"activate": True}},
        {"step": "writeFile", "path": "/wordpress/wp-content/mu-plugins/jasrek-esikatselu.php", "data": read('preview-notice.php')},
        {"step": "runPHP", "code": read('demo-seed.php')},
    ],
}
out = os.path.join(d, 'blueprint.json')
with open(out, 'w', encoding='utf-8') as f:
    json.dump(bp, f, ensure_ascii=False, indent=2)
    f.write('\n')
print('ok', out, os.path.getsize(out), 'tavua')
