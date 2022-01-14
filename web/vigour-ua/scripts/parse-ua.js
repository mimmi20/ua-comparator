const initStart = process.hrtime();
const ua = require('vigour-ua');
// Trigger a parse to force cache loading
ua('Test String');
const initTime = process.hrtime(initStart)[1] / 1000000000;

const packageInfo = require(require('path').dirname(
    require.resolve('vigour-ua')
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
    let r = null;
    try {
        r = ua(line);
    } catch (err) {
        output.result.err = [
            err.name,
            err.message,
            err.stack
        ];
    }

    const end = process.hrtime(start)[1] / 1000000000;

    if (r !== null) {
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
                type: r.device,
                simCount: null,
                ismobile:
                    r.device === 'phone' ||
                    r.device === 'mobile' ||
                    r.device === 'tablet' ||
                    r.device === 'wearable'
            },
            client: {
                name: (r.browser && r.browser !== true) ? r.browser : null,
                modus: null,
                version: r.version ? r.version : null,
                manufacturer: null,
                bits: null,
                type: null,
                isbot: null
            },
            platform: {
                name: r.platform,
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
    }
    output.parse_time = end;
}

output.memory_used = process.memoryUsage().heapUsed;
console.log(JSON.stringify(output, null, 2));
