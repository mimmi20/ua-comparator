import parser from 'woothee';
import packageInfo from 'woothee/package.json' with { type: 'json' };

const initStart = process.hrtime();
// Trigger a parse to force cache loading
parser.parse('Test String');
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
  const r = parser.parse(line);
  const end = process.hrtime(start)[1] / 1000000000;

  output.result.parsed = {
    device: {
      architecture: null,
      deviceName: null,
      marketingName: null,
      manufacturer: null,
      brand: null,
      dualOrientation: null,
      simCount: null,
      display: {
        width: null,
        height: null,
        touch: null,
        type: null,
        size: null,
      },
      type: r.category && r.category !== 'UNKNOWN' ? r.category : null,
      ismobile: null,
      istv: null,
      bits: null,
    },
    client: {
      name: r.name && r.name !== 'UNKNOWN' ? r.name : null,
      modus: null,
      version: r.version && r.version !== 'UNKNOWN' ? r.version : null,
      manufacturer: r.vendor && r.vendor !== 'UNKNOWN' ? r.vendor : null,
      bits: null,
      isbot: null,
      type: null,
    },
    platform: {
      name: r.os && r.os !== 'UNKNOWN' ? r.os : null,
      marketingName: null,
      version: r.os_version && r.os_version !== 'UNKNOWN' ? r.os_version : null,
      manufacturer: null,
      bits: null,
    },
    engine: {
      name: null,
      version: null,
      manufacturer: null,
    },
    raw: r,
  };
  output.parse_time = end;
}

output.memory_used = process.memoryUsage().heapUsed;
console.log(JSON.stringify(output, null, 2));
