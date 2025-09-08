import parser from 'vigour-ua';
import packageInfo from 'vigour-ua/package.json' with { type: 'json' };

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
  let r = null;
  try {
    r = parser(line);
  } catch (err) {
    output.result.err = {
      name: err.name,
      message: err.message,
      stack: err.stack,
    };
  }

  const end = process.hrtime(start)[1] / 1000000000;

  if (r !== null) {
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
        type: r.device,
        ismobile: r.device === 'phone' || r.device === 'mobile' || r.device === 'tablet' || r.device === 'wearable',
        istv: null,
        bits: null,
      },
      client: {
        name: r.browser && r.browser !== true ? r.browser : null,
        modus: null,
        version: r.version ? r.version : null,
        manufacturer: null,
        bits: null,
        isbot: null,
        type: null,
      },
      platform: {
        name: r.platform,
        marketingName: null,
        version: null,
        manufacturer: null,
        bits: null,
      },
      engine: {
        name: r.prefix ? r.prefix : null,
        version: null,
        manufacturer: null,
      },
      raw: r,
    };
  }
  output.parse_time = end;
}

output.memory_used = process.memoryUsage().heapUsed;
console.log(JSON.stringify(output, null, 2));
