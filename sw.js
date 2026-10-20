/**
 * Copyright 2018 Google Inc. All Rights Reserved.
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *     http://www.apache.org/licenses/LICENSE-2.0
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

// If the loader is already loaded, just stop.
if (!self.define) {
  let registry = {};

  // Used for `eval` and `importScripts` where we can't get script URL by other means.
  // In both cases, it's safe to use a global var because those functions are synchronous.
  let nextDefineUri;

  const singleRequire = (uri, parentUri) => {
    uri = new URL(uri + ".js", parentUri).href;
    return registry[uri] || (
      
        new Promise(resolve => {
          if ("document" in self) {
            const script = document.createElement("script");
            script.src = uri;
            script.onload = resolve;
            document.head.appendChild(script);
          } else {
            nextDefineUri = uri;
            importScripts(uri);
            resolve();
          }
        })
      
      .then(() => {
        let promise = registry[uri];
        if (!promise) {
          throw new Error(`Module ${uri} didn’t register its module`);
        }
        return promise;
      })
    );
  };

  self.define = (depsNames, factory) => {
    const uri = nextDefineUri || ("document" in self ? document.currentScript.src : "") || location.href;
    if (registry[uri]) {
      // Module is already loading or loaded.
      return;
    }
    let exports = {};
    const require = depUri => singleRequire(depUri, uri);
    const specialDeps = {
      module: { uri },
      exports,
      require
    };
    registry[uri] = Promise.all(depsNames.map(
      depName => specialDeps[depName] || require(depName)
    )).then(deps => {
      factory(...deps);
      return exports;
    });
  };
}
define(['./workbox-e3f1c39f'], (function (workbox) { 'use strict';

  importScripts("sw-heal.js");
  self.skipWaiting();
  workbox.clientsClaim();
  /**
   * The precacheAndRoute() method efficiently caches and responds to
   * requests for URLs in the manifest.
   * See https://goo.gl/S9QRab
   */
  workbox.precacheAndRoute([{
    "url": "registerSW.js",
    "revision": "402b66900e731ca748771b6fc5e7a068"
  }, {
    "url": "assets/virtualization.-e2f62KR.js",
    "revision": null
  }, {
    "url": "assets/vendor.CYCv-W1W.js",
    "revision": null
  }, {
    "url": "assets/validation.OCGaXQWG.js",
    "revision": null
  }, {
    "url": "assets/utilities.Ce8HPfek.js",
    "revision": null
  }, {
    "url": "assets/undockedWindow.CyPnGwVV.js",
    "revision": null
  }, {
    "url": "assets/tanstack-vendor.CnpLBY0V.js",
    "revision": null
  }, {
    "url": "assets/shared-libs.CKPm2yyl.js",
    "revision": null
  }, {
    "url": "assets/security-ui.1QlBetgL.js",
    "revision": null
  }, {
    "url": "assets/sandpack.CxUPS3bj.js",
    "revision": null
  }, {
    "url": "assets/runtime-shims.CfYCxqfF.js",
    "revision": null
  }, {
    "url": "assets/routing.B9pNiaaQ.js",
    "revision": null
  }, {
    "url": "assets/rolldown-runtime.C0FnF6B9.js",
    "revision": null
  }, {
    "url": "assets/react-vtree.B8RSo9H9.js",
    "revision": null
  }, {
    "url": "assets/react-interactions.Dx3j_bf1.js",
    "revision": null
  }, {
    "url": "assets/radix-ui.Bk6h9nE4.js",
    "revision": null
  }, {
    "url": "assets/queries.ayl4eyYe.js",
    "revision": null
  }, {
    "url": "assets/polyfills.DVBsJvPx.js",
    "revision": null
  }, {
    "url": "assets/mermaid.DZbUXogC.js",
    "revision": null
  }, {
    "url": "assets/math-katex.CMgqFgOO.js",
    "revision": null
  }, {
    "url": "assets/math-katex.C5_YvI3e.css",
    "revision": null
  }, {
    "url": "assets/markdown.DvBZaCcY.js",
    "revision": null
  }, {
    "url": "assets/markdown-processing.0pIrMYCI.js",
    "revision": null
  }, {
    "url": "assets/lazy.HJ4iVRdz.js",
    "revision": null
  }, {
    "url": "assets/index.SO5CUjtE.css",
    "revision": null
  }, {
    "url": "assets/index.CQgpzTuw.js",
    "revision": null
  }, {
    "url": "assets/i18n.Rj7iy5Yf.js",
    "revision": null
  }, {
    "url": "assets/http-client.D6G8y4MX.js",
    "revision": null
  }, {
    "url": "assets/hooks.DiXgTzJF.js",
    "revision": null
  }, {
    "url": "assets/heicConverter.CfXXBx7A.js",
    "revision": null
  }, {
    "url": "assets/heic-converter.Yxm-SPCR.js",
    "revision": null
  }, {
    "url": "assets/headlessui.D99OgoBK.js",
    "revision": null
  }, {
    "url": "assets/framer-motion.7qf7m3dK.js",
    "revision": null
  }, {
    "url": "assets/forms.DwDAuOFP.js",
    "revision": null
  }, {
    "url": "assets/forms.DHWSw7Q9.js",
    "revision": null
  }, {
    "url": "assets/duringRun.C59Dx32e.js",
    "revision": null
  }, {
    "url": "assets/date-utils.C_DbPEjY.js",
    "revision": null
  }, {
    "url": "assets/codemirror-core.B3ddRMLP.js",
    "revision": null
  }, {
    "url": "assets/code-editor.CQ9tYa-j.js",
    "revision": null
  }, {
    "url": "assets/avatars.XYysrFin.js",
    "revision": null
  }, {
    "url": "assets/animations.BvK6RgDr.js",
    "revision": null
  }, {
    "url": "assets/advanced-inputs.D3aoJ9lo.js",
    "revision": null
  }, {
    "url": "assets/SubagentThreadPanel.Dzz1W_Vc.js",
    "revision": null
  }, {
    "url": "assets/Stage.B1uOYOK0.js",
    "revision": null
  }, {
    "url": "assets/SkillsView.BIL3fEHc.js",
    "revision": null
  }, {
    "url": "assets/Settings.YFAJXL7M.js",
    "revision": null
  }, {
    "url": "assets/SandboxArtifactTabs.CWpLtcBA.js",
    "revision": null
  }, {
    "url": "assets/Projects.CmYRK5Bp.js",
    "revision": null
  }, {
    "url": "assets/ProjectEditDialog.BeTCfNAk.js",
    "revision": null
  }, {
    "url": "assets/MessageContent.B339VXio.js",
    "revision": null
  }, {
    "url": "assets/Insights.D-vcbSsg.js",
    "revision": null
  }, {
    "url": "assets/ImagePreview.B8fSVo2x.js",
    "revision": null
  }, {
    "url": "assets/Footer.CRq4c_Xn.css",
    "revision": null
  }, {
    "url": "assets/Footer.Bne5q2lA.js",
    "revision": null
  }, {
    "url": "assets/Dialog.D54iZh5w.js",
    "revision": null
  }, {
    "url": "assets/Artifacts.3CLLjimW.js",
    "revision": null
  }, {
    "url": "assets/AccountSettings.C9eAuifz.js",
    "revision": null
  }, {
    "url": "assets/favicon-32x32.png",
    "revision": "eb123edfbf0477095fce9a584c3552bf"
  }, {
    "url": "assets/favicon-16x16.png",
    "revision": "576d6a43e8f233d830de116024acf33d"
  }, {
    "url": "assets/icon-192x192.png",
    "revision": "defb5ac1edae8315e15bd8053fed102a"
  }, {
    "url": "assets/apple-touch-icon-180x180.png",
    "revision": "0265dbac793fe970021036ddfa50861e"
  }, {
    "url": "assets/maskable-icon.png",
    "revision": "dbe693d9dec612cbb6e6c146e8b73cb3"
  }, {
    "url": "manifest.webmanifest",
    "revision": "0d285a51efd12fdba7f1fd10e3655e09"
  }], {});
  workbox.cleanupOutdatedCaches();
  workbox.registerRoute(({
    url
  }) => /\/assets\/locale-[^/]+\.js$/.test(url.pathname), new workbox.StaleWhileRevalidate({
    "cacheName": "locale-chunks",
    plugins: [new workbox.CacheableResponsePlugin({
      statuses: [0, 200]
    }), new workbox.ExpirationPlugin({
      maxEntries: 80,
      maxAgeSeconds: 2592000
    })]
  }), 'GET');

}));
//# sourceMappingURL=sw.js.map
//# sourceMappingURL=sw.js.map
