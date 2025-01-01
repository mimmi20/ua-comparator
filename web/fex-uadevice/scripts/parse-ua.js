const initStart = process.hrtime();
const parser = require('ua-device');
// Trigger a parse to force cache loading
const tmp = new parser('Test String');
const initTime = process.hrtime(initStart)[1] / 1000000000;

const package = require(require('path').dirname(require.resolve('ua-device')) +
    '/package.json');
const version = package.version;

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
    let r = null;
    try {
        r = new parser(line);
    } catch (err) {
        output.result.err = {
            name: err.name,
            message: err.message,
            stack: err.stack
        };
    }

    const end = process.hrtime(start)[1] / 1000000000;

    if (r !== null) {
        output.result.parsed = {
            device: {
                deviceName: r.device.model ? r.device.model : null,
                marketingName: null,
                manufacturer: null,
                brand: r.device.manufacturer ? r.device.manufacturer : null,
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
                ismobile:
                    r.device.type === 'mobile' ||
                    r.device.type === 'tablet' ||
                    r.device.type === 'wearable'
            },
            client: {
                name: r.browser.name ? r.browser.name : null,
                modus: null,
                version:
                    typeof r.browser.version !== 'undefined' &&
                    r.browser.version !== null &&
                    typeof r.browser.version.original !== 'undefined'
                        ? r.browser.version.original
                        : null,
                manufacturer: null,
                bits: null,
                type: null,
                isbot: null
            },
            platform: {
                name: r.os.name ? r.os.name : null,
                marketingName: null,
                version:
                    typeof r.os.version !== 'undefined' &&
                    r.os.version !== null &&
                    typeof r.os.version.original !== 'undefined'
                        ? r.os.version.original
                        : null,
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
    }
    output.parse_time = end;
}

output.memory_used = process.memoryUsage().heapUsed;
console.log(JSON.stringify(output, null, 2));
