import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate } from 'k6/metrics';

const errorRate = new Rate('errors');

export let options = {
    stages: [
        { duration: '10s', target: 100 },
        { duration: '30s', target: 100 },
        { duration: '10s', target: 500 },
        { duration: '30s', target: 500 },
        { duration: '10s', target: 1000 },
        { duration: '1m', target: 1000 },
        { duration: '10s', target: 0 },
    ],
    thresholds: {
        'http_req_duration': ['p(95)<100', 'p(99)<200'],
        'errors': ['rate<0.01'],
    },
};

const BASE_URL = __ENV.BASE_URL || 'http://app:8000';
const PLAYER_COUNT = 10000;

export default function () {
    let playerId = `player_${Math.floor(Math.random() * PLAYER_COUNT) + 1}`;
    let score = Math.floor(Math.random() * 2000) + 100;
    
    let updateRes = http.post(
        `${BASE_URL}/api/players/${playerId}/score`,
        JSON.stringify({ score }),
        {
            headers: { 'Content-Type': 'application/json' },
            tags: { name: 'UpdateScore' },
        }
    );
    
    let updateSuccess = check(updateRes, {
        'update score status 200': (r) => r.status === 200,
        'update score response time < 100ms': (r) => r.timings.duration < 100,
    }) || check(updateRes, {
        'update score status 400': (r) => r.status === 400,
    });
    
    errorRate.add(!updateSuccess);
    
    sleep(0.01);

    let rankRes = http.get(
        `${BASE_URL}/api/players/${playerId}/rank`,
        { tags: { name: 'GetPlayerRank' } }
    );
    
    let rankSuccess = check(rankRes, {
        'get rank status 200': (r) => r.status === 200,
        'get rank response time < 100ms': (r) => r.timings.duration < 100,
        'get rank has correct score': (r) => {
            if (r.status === 200) {
                let data = JSON.parse(r.body);
                return data.score === score;
            }
            return false;
        },
    });
    
    errorRate.add(!rankSuccess);
    
    let limit = Math.floor(Math.random() * 100) + 1;
    let topRes = http.get(
        `${BASE_URL}/api/leaderboard/top?limit=${limit}`,
        { tags: { name: 'GetTopPlayers' } }
    );
    
    let topSuccess = check(topRes, {
        'get top players status 200': (r) => r.status === 200,
        'get top players response time < 100ms': (r) => r.timings.duration < 100,
        'get top players has players array': (r) => {
            if (r.status === 200) {
                let data = JSON.parse(r.body);
                return Array.isArray(data.players);
            }
            return false;
        },
    });
    
    errorRate.add(!topSuccess);
    
    if (updateRes.status === 200 && rankRes.status === 200) {
        let rankData = JSON.parse(rankRes.body);
        let updateData = JSON.parse(updateRes.body);
        
        check(null, {
            'real-time rank update': () => {
                return rankData.rank !== undefined && rankData.score === updateData.score;
            },
        });
    }
    
    sleep(0.01);
}

export function handleSummary(data) {
    return {
        'stdout': textSummary(data, { indent: ' ', enableColors: true }),
        'tests/k6/report.html': htmlReport(data),
    };
}

function textSummary(data, options) {
    return `
    ====================
    Load Test Summary
    ====================
    Total Requests: ${data.metrics.http_reqs.values.count}
    Failed Requests: ${data.metrics.http_req_failed ? data.metrics.http_req_failed.values.rate * 100 : 0}%
    Average Response Time: ${data.metrics.http_req_duration.values.avg.toFixed(2)}ms
    P95 Response Time: ${data.metrics.http_req_duration.values['p(95)'].toFixed(2)}ms
    P99 Response Time: ${data.metrics.http_req_duration.values['p(99)'].toFixed(2)}ms
    ====================
    `;
}

function htmlReport(data) {
    return `
    <!DOCTYPE html>
    <html>
    <head><title>K6 Load Test Report</title></head>
    <body>
        <h1>Load Test Results</h1>
        <pre>${JSON.stringify(data, null, 2)}</pre>
    </body>
    </html>
    `;
}

