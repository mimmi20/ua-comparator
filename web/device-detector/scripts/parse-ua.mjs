import DeviceDetector from 'device-detector-js';
import packageInfo from 'device-detector-js/package.json' with { type: 'json' };

const initStart = process.hrtime();
const detector = new DeviceDetector({ skipBotDetection: true, cache: false });
// Trigger a parse to force cache loading
detector.parse('Test String');
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
    r = detector.parse(line);
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
        deviceName: r.device && r.device.model ? r.device.model : null,
        marketingName: null,
        manufacturer: null,
        brand: r.device && r.device.brand ? r.device.brand : null,
        dualOrientation: null,
        simCount: null,
        display: {
          width: null,
          height: null,
          touch: null,
          type: null,
          size: null,
        },
        type: r.device && r.device.type ? r.device.type : null,
        ismobile: r.device && (r.device.type === 'mobile' || r.device.type === 'mobilephone' || r.device.type === 'tablet' || r.device.type === 'wearable' || r.device.type === 'smartphone'),
        istv: null,
        bits: null,
      },
      client: {
        name: r.client && r.client.name ? r.client.name : null,
        modus: null,
        version: r.client && r.client.version ? r.client.version : null,
        manufacturer: null,
        bits: null,
        isbot: null,
        type: null,
      },
      platform: {
        name: r.os && r.os.name ? r.os.name : null,
        marketingName: null,
        version: r.os && r.os.version ? r.os.version : null,
        manufacturer: null,
        bits: null,
      },
      engine: {
        name: r.client && r.client.engine ? r.client.engine : null,
        version: r.client && r.client.engineVersion ? r.client.engineVersion : null,
        manufacturer: null,
      },
      raw: r,
    };
  }
  output.parse_time = end;
}

output.memory_used = process.memoryUsage().heapUsed;
console.log(JSON.stringify(output, null, 2));
