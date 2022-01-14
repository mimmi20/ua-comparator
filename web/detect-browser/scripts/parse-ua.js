const initStart = process.hrtime();
const { parseUserAgent } = require('detect-browser');
// Trigger a parse to force cache loading
parseUserAgent('Test String');
const initTime = process.hrtime(initStart)[1] / 1000000000;

const packageInfo = require(require('path').dirname(
    require.resolve('detect-browser')
) + '/package.json');
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
    const r = parseUserAgent(line);
    const end = process.hrtime(start)[1] / 1000000000;

    output.result.parsed = {
        device: {
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
        },
        client: {
            name: r !== null && r.name ? r.name : null,
            modus: null,
            version: r !== null && r.version ? r.version : null,
            manufacturer: null,
            bits: null,
            type: null,
            isbot: null
        },
        platform: {
            name: r !== null && r.os ? r.os : null,
            marketingName: null,
            version: null,
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
