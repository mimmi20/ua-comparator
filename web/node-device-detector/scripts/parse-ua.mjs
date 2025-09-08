import DeviceDetector from 'node-device-detector';
import DeviceHelper from 'node-device-detector/helper.js';
import packageInfo from 'node-device-detector/package.json' with { type: 'json' };

const initStart = process.hrtime();
const detector = new DeviceDetector({
  clientIndexes: true,
  deviceIndexes: true,
  osIndexes: true,
  deviceAliasCode: false,
  deviceTrusted: false,
  deviceInfo: false,
  maxUserAgentSize: 500,
});
// Trigger a parse to force cache loading
detector.detect('Test String');
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
  const r = detector.detect(line);
  const bot = detector.parseBot(line);
  const isNotABot = (Array.isArray(bot) && bot.length === 0) || JSON.stringify(bot) === '{}';
  const isMobile = DeviceHelper.isMobile(r);
  const end = process.hrtime(start)[1] / 1000000000;

  output.result.parsed = {
    device: {
      architecture: null,
      deviceName: r.device.model ?? null,
      marketingName: null,
      manufacturer: null,
      brand: r.device.vendor ?? null,
      dualOrientation: null,
      simCount: null,
      display: {
        width: null,
        height: null,
        touch: null,
        type: null,
        size: null,
      },
      type: r.device.type ?? null,
      ismobile: isMobile,
      istv: null,
      bits: null,
    },
    client: {
      name: r.client.name ?? null,
      modus: null,
      version: r.client.version ?? null,
      manufacturer: null,
      bits: null,
      isbot: !isNotABot,
      type: r.client.type ?? null,
    },
    platform: {
      name: r.os.name ?? null,
      marketingName: null,
      version: r.os.version ?? null,
      manufacturer: null,
      bits: null,
    },
    engine: {
      name: r.client.engine ?? null,
      version: r.client.engine_version ?? null,
      manufacturer: null,
    },
    raw: [r, bot],
  };
  output.parse_time = end;
}

output.memory_used = process.memoryUsage().heapUsed;
console.log(JSON.stringify(output, null, 2));
