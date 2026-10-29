#!/usr/bin/env node
/**
 * Export Archify canonical PNG (diagram only, no viewer chrome) via Chrome CDP.
 * Usage: node export-canonical-png.mjs [htmlPath] [outPng]
 */
import fs from "node:fs";
import path from "node:path";
import { pathToFileURL } from "node:url";
import {
    ChromeVisualBrowser,
    findChrome,
} from "file:///C:/Users/jcali/.agents/skills/archify/bin/visual-check.mjs";

const htmlPath = path.resolve(
    process.argv[2] ||
        "C:/laragon/www/centinela-public/docs/architecture/platform/flujo-entrega-produccion/centinela-flujo-entrega-produccion.html",
);
const outPng = path.resolve(
    process.argv[3] ||
        path.join(
            path.dirname(htmlPath),
            "centinela-flujo-entrega-produccion.export.png",
        ),
);
const theme = "light";

async function evaluate(cdp, sessionId, expression, awaitPromise = false) {
    const result = await cdp.send(
        "Runtime.evaluate",
        {
            expression,
            awaitPromise,
            returnByValue: true,
        },
        sessionId,
        60000,
    );
    if (result.exceptionDetails) {
        const msg =
            result.exceptionDetails.exception?.description ||
            result.exceptionDetails.text ||
            "Runtime.evaluate failed";
        throw new Error(msg);
    }
    return result.result?.value;
}

async function main() {
    if (!fs.existsSync(htmlPath)) {
        throw new Error(`Missing HTML: ${htmlPath}`);
    }

    const chromePath = findChrome();
    if (!chromePath) {
        throw new Error("Chrome/Chromium not found (set ARCHIFY_CHROME)");
    }

    const browser = new ChromeVisualBrowser(chromePath);
    try {
        const sessionId = await browser.sessionPromise;
        await browser.cdp.send(
            "Emulation.setDeviceMetricsOverride",
            {
                width: 1600,
                height: 1000,
                deviceScaleFactor: 1,
                mobile: false,
            },
            sessionId,
        );

        const url = new URL(pathToFileURL(htmlPath).href);
        url.searchParams.set("theme", theme);
        const loaded = browser.cdp.waitFor("Page.loadEventFired", sessionId);
        const navigation = await browser.cdp.send(
            "Page.navigate",
            { url: url.href },
            sessionId,
        );
        if (navigation.errorText) {
            throw new Error(`Navigation failed: ${navigation.errorText}`);
        }
        await loaded;

        await evaluate(
            browser.cdp,
            sessionId,
            `(async function () {
      document.documentElement.setAttribute('data-motion', 'still');
      var panel = document.querySelector('.diagram-container');
      if (panel) panel.setAttribute('data-detail-level', 'read');
      if (document.fonts && document.fonts.ready) {
        try { await document.fonts.ready; } catch (_) {}
      }
      for (var i = 0; i < 40; i++) {
        if (window.Archify && Archify.exportMenu && typeof Archify.exportMenu.run === 'function') break;
        await new Promise(function (r) { setTimeout(r, 50); });
      }
      if (!(window.Archify && Archify.exportMenu)) {
        throw new Error('Archify.exportMenu not ready');
      }
      if (Archify.readerLayout && typeof Archify.readerLayout.whenStable === 'function') {
        await Archify.readerLayout.whenStable();
      }
    })()`,
            true,
        );

        const b64 = await evaluate(
            browser.cdp,
            sessionId,
            `(async function () {
      return await new Promise(function (resolve, reject) {
        var settled = false;
        var timer = setTimeout(function () {
          if (!settled) reject(new Error('PNG export timed out'));
        }, 45000);
        var finish = function (value, err) {
          if (settled) return;
          settled = true;
          clearTimeout(timer);
          if (err) reject(err);
          else resolve(value);
        };
        var origCreate = URL.createObjectURL.bind(URL);
        URL.createObjectURL = function (blob) {
          var objectUrl = origCreate(blob);
          if (blob && blob.type && blob.type.indexOf('image/png') === 0) {
            blob.arrayBuffer().then(function (buf) {
              var bytes = new Uint8Array(buf);
              var chunk = 0x8000;
              var binary = '';
              for (var i = 0; i < bytes.length; i += chunk) {
                binary += String.fromCharCode.apply(null, bytes.subarray(i, i + chunk));
              }
              finish(btoa(binary));
            }).catch(function (err) { finish(null, err); });
          }
          return objectUrl;
        };
        Archify.exportMenu.run('png').catch(function (err) { finish(null, err); });
      });
    })()`,
            true,
        );

        if (!b64 || typeof b64 !== "string") {
            throw new Error("Export returned empty PNG payload");
        }

        fs.writeFileSync(outPng, Buffer.from(b64, "base64"));
        console.log(
            JSON.stringify(
                {
                    ok: true,
                    theme,
                    output: outPng,
                    bytes: fs.statSync(outPng).size,
                },
                null,
                2,
            ),
        );
    } finally {
        try {
            browser.child.kill();
        } catch (_) {}
    }
}

main().catch((err) => {
    console.error(err);
    process.exit(1);
});
