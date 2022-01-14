#!/usr/bin/env node

const initStart = process.hrtime();
const DeviceDetector = require('node-device-detector');
const DeviceHelper = require('node-device-detector/helper');
const detector = new DeviceDetector();
// Trigger a parse to force cache loading
detector.detect('Test String');
const initTime = process.hrtime(initStart)[1] / 1000000000;

const packageInfo = require(require('path').dirname(require.resolve('node-device-detector')) +
    '/package.json');
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
        "user-agent": line
    },
    result: {
        parsed: null,
        err: null
    },
    parse_time: 0,
    init_time: initTime,
    memory_used: 0,
    version: version
};

if (hasUa) {
    const start = process.hrtime();
    const r = detector.detect(line);
    const bot = detector.parseBot(line);
    const end = process.hrtime(start)[1] / 1000000000;

    output.result.parsed = {
        device: {
            deviceName: r.device.model ? r.device.model : null,
            marketingName: null,
            manufacturer: null,
            brand: r.device.vendor ? r.device.vendor : null,
            display: {
                width: null,
                height: null,
                touch: null,
                type: null,
                size: null,
            },
            dualOrientation: null,
            type: r.device.type ? r.device.type : null,
            simCount: null,
            ismobile: DeviceHelper.isMobile(r)
        },
        client: {
            name: bot === null ? (r.client.name ? r.client.name : null) : (bot.name ?? null),
            modus: null,
            version: (bot !== null && r.client.version) ? r.client.version : null,
            manufacturer: null,
            bits: null,
            isBot: bot !== null,
            type: bot === null ? (r.client.type ?? null) : (bot.category ?? null)
        },
        platform: {
            name: r.os.name ? r.os.name : null,
            marketingName: null,
            version: r.os.version ? r.os.version : null,
            manufacturer: null,
            bits: null
        },
        engine: {
            name: null,
            version: null,
            manufacturer: null
        },
        raw: r
    };
    output.parse_time = end;
}

output.memory_used = process.memoryUsage().heapUsed;
console.log(JSON.stringify(output, null, 2));
