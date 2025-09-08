import WhichBrowser from 'which-browser';
import packageInfo from 'which-browser/package.json' with { type: 'json' };

const initStart = process.hrtime();
// Trigger a parse to force cache loading
new WhichBrowser('Test String');
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
    r = new WhichBrowser(line);
  } catch (err) {
    output.result.err = {
      name: err.name,
      message: err.message,
      stack: err.stack,
    };
  }

  const end = process.hrtime(start)[1] / 1000000000;

  const mobileDeviceTypes = ['mobile', 'tablet', 'watch', 'media', 'ereader', 'camera'];

  if (r !== null) {
    output.result.parsed = {
      device: {
        architecture: null,
        deviceName: r.device.model ? r.device.model : null,
        marketingName: null,
        manufacturer: null,
        brand: r.device.manufacturer ? r.device.manufacturer : null,
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
        ismobile: mobileDeviceTypes.indexOf(r.device.type) !== -1 || (r.device.subtype && r.device.subtype === 'portable'),
        istv: null,
        bits: null,
      },
      client: {
        name: r.browser.name ? r.browser.name : null,
        modus: null,
        version: r.browser.version ? r.browser.version.value : null,
        manufacturer: null,
        bits: null,
        type: null,
        isbot: null,
      },
      platform: {
        name: r.os.name ? r.os.name : null,
        marketingName: null,
        version: r.os.version && r.os.version.value ? r.os.version.value : null,
        manufacturer: null,
        bits: null,
      },
      engine: {
        name: r.engine.name ? r.engine.name : null,
        version: r.engine.version && r.engine.version.value ? r.engine.version.value : null,
        manufacturer: null,
      },
      raw: r,
    };
  }
  output.parse_time = end;
}

output.memory_used = process.memoryUsage().heapUsed;
console.log(JSON.stringify(output, null, 2));
