const initStart = process.hrtime();
const parser = require('useragent');
parser(true);

// Trigger a parse to force cache loading
parser.parse('Test String');
const initTime = process.hrtime(initStart)[1] / 1000000000;

const packageInfo = require(require('path').dirname(require.resolve('useragent')) +
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
    const r = parser.parse(line),
        os = r.os,
        device = r.device;
    const end = process.hrtime(start)[1] / 1000000000;

    const outputDevice = {
        deviceName: null,
        marketingName: null,
        manufacturer: null,
        brand: null,
        display: {
            width: null,
            height: null,
            touch: null,
            type: null,
            size: null,
        },
        dualOrientation: null,
        type: null,
        simCount: null,
        ismobile: null
    };

    if (device.major !== '0') {
        outputDevice.deviceName = device.major;
        outputDevice.brand = device.family;
    } else if (device.family !== 'Other') {
        outputDevice.deviceName = device.family;
    }

    output.result.parsed = {
        device: outputDevice,
        client: {
            name: r.family === 'Other' ? null : r.family,
            modus: null,
            version: (r.family === 'Other' || r.toVersion() === '0.0.0') ? null : r.toVersion(),
            manufacturer: null,
            bits: null,
            type: null,
            isbot: null
        },
        platform: {
            name: os.family === 'Other' ? null : os.family,
            marketingName: null,
            version: (os.family === 'Other' || r.os.toVersion() === '0.0.0') ? null : r.os.toVersion(),
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
