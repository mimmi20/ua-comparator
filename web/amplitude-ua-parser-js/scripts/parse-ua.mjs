#!/usr/bin/env node

import parser from '@amplitude/ua-parser-js';
import packageInfo from '@amplitude/ua-parser-js/package.json' with { type: 'json' };

const initStart = process.hrtime();

// Trigger a parse to force cache loading
parser('Test String');
const initTime = process.hrtime(initStart)[1] / 1000000000;

const version = packageInfo.version;

let hasUa = false;
const uaPos = process.argv.indexOf('--ua');
let line = '';
if (uaPos >= 0) {
  line = process.argv[3];
  hasUa = true;
}

const output = {
  hasUa: hasUa,
  headers: {
    'user-agent': line,
  },
  result: {
    parsed: null,
    err: null,
  },
  parse_time: 0,
  init_time: initTime,
  memory_used: 0,
  version: version,
};

if (hasUa) {
  const start = process.hrtime();
  const r = parser(line);
  const end = process.hrtime(start)[1] / 1000000000;

  output.result.parsed = {
    device: {
      architecture: null,
      deviceName: r.device.model ? r.device.model : null,
      marketingName: null,
      manufacturer: null,
      brand: r.device.vendor ? r.device.vendor : null,
      dualOrientation: null,
      simCount: null,
      display: {
        width: null,
        height: null,
        touch: null,
        type: null,
        size: null,
      },
      type: r.device.type ? r.device.type : null,
      ismobile: r.device.type === 'mobile' || r.device.type === 'tablet' || r.device.type === 'wearable',
      istv: null,
      bits: null,
    },
    client: {
      name: r.browser.name ? r.browser.name : null,
      modus: null,
      version: r.browser.version ? r.browser.version : null,
      manufacturer: null,
      bits: null,
      isbot: null,
      type: null,
    },
    platform: {
      name: r.os.name ? r.os.name : null,
      marketingName: null,
      version: r.os.version ? r.os.version : null,
      manufacturer: null,
      bits: null,
    },
    engine: {
      name: r.engine.name ? r.engine.name : null,
      version: r.engine.version ? r.engine.version : null,
      manufacturer: null,
    },
    raw: r,
  };
  output.parse_time = end;
}

output.memory_used = process.memoryUsage().heapUsed;
console.log(JSON.stringify(output, null, 2));
