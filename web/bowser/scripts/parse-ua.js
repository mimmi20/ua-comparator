const initStart = process.hrtime();
const parser = require('bowser');
// Trigger a parse to force cache loading
const r = parser.getParser('Test String');
r.parse();
const initTime = process.hrtime(initStart)[1] / 1000000000;

const packageInfo = require(require('path').dirname(require.resolve('bowser')) +
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
    const browser = parser.getParser(line);
    const r = browser.parse().parsedResult;
    const end = process.hrtime(start)[1] / 1000000000;

    output.result.parsed = {
        device: {
            deviceName: r.platform.model ? r.platform.model : null,
            marketingName: null,
            manufacturer: null,
            brand: r.platform.vendor ? r.platform.vendor : null,
            display: {
                width: null,
                height: null,
                touch: null,
                type: null,
                size: null,
            },
            dualOrientation: null,
            type: r.platform.type ? r.platform.type : null,
            simCount: null,
            ismobile:
                r.platform.type === 'mobile' ||
                r.platform.type === 'tablet' ||
                r.platform.type === 'wearable'
        },
        client: {
            name: r.browser.name ? r.browser.name : null,
            modus: null,
            version: r.browser.version ? r.browser.version : null,
            manufacturer: null,
            bits: null,
            type: null,
            isbot: null
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
