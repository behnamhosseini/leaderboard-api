// /scripts/max_updates_per_second_test.js
// Purpose: Measure the REAL maximum number of score updates your system can handle in exactly 1 second
// This is a "burst throughput" test with 100 concurrent VUs hammering the endpoint non-stop for 1 second

import http from 'k6/http';
import { check } from 'k6';
import { SharedArray } from 'k6/data';

export const options = {
    vus: 100,                    // 100 concurrent virtual users
    duration: '1s',              // Run exactly 1 second
    iterations: null,            // Unlimited iterations – send as many requests as possible

    thresholds: {
        'http_req_duration': ['p(95)<500', 'max<1000'], // Keep latency reasonable
        'http_req_failed': ['rate<0.01'],               // Error rate must stay < 1%
        'http_reqs': ['count>100'],                     // Must exceed the original requirement
    },
};

// Pre-generate 10,000 player IDs to eliminate random-generation overhead
const players = new SharedArray('players', function () {
    const arr = [];
    for (let i = 1; i <= 10000; i++) {
        arr.push(`player_${i}`);
    }
    return arr;
});

const BASE_URL = __ENV.BASE_URL || 'http://nginx';

export default function () {
    const playerId = players[Math.floor(Math.random() * players.length)];
    const score = Math.floor(Math.random() * 2000) + 100;

    const res = http.post(
        `${BASE_URL}/api/players/${playerId}/score`,
        JSON.stringify({ score }),
        {
            headers: { 'Content-Type': 'application/json' },
            tags: { name: 'UpdateScore' },
        }
    );

    check(res, {
        'status is 200': (r) => r.status === 200,
        'response time < 1000ms': (r) => r.timings.duration < 1000,
    });

    // No sleep() → maximum possible throughput
}

export function handleSummary(data) {
    const totalRequests = data.metrics.http_reqs?.values.count || 0;
    const failedRate = data.metrics.http_req_failed?.values.rate || 0;
    const successRate = ((1 - failedRate) * 100).toFixed(2);
    const duration = data.metrics.http_req_duration?.values || {};

    const avg = duration.avg ? duration.avg.toFixed(2) : 'N/A';
    const p95 = duration['p(95)'] ? duration['p(95)'].toFixed(2) : 'N/A';
    const max = duration.max ? duration.max.toFixed(2) : 'N/A';

    const throughputPerSecond = Math.round(totalRequests); // already in 1s window

    const healthy = failedRate < 0.01 && (duration.max || 0) < 1000;

    return {
        stdout: `
╔══════════════════════════════════════════════════════════════╗
║           MAXIMUM UPDATES PER SECOND – 1 SECOND BURST        ║
╚══════════════════════════════════════════════════════════════╝

Duration           : 1 second (fixed)
Concurrent VUs     : 100
Total Requests     : ${totalRequests}
Throughput         : ${throughputPerSecond} updates/second

Success Rate       : ${successRate}%
Error Rate         : ${(failedRate * 100).toFixed(4)}%

Latency
├─ Average          : ${avg} ms
├─ P95              : ${p95} ms
└─ Maximum          : ${max} ms

Health Check       : ${healthy ? 'PASS (system healthy under max load)' : 'FAIL (latency/error spike)'}

Conclusion:
   Your leaderboard can sustain ≈ ${throughputPerSecond} score updates per second
   with 100 concurrent connections and excellent latency.

   This is your real production ceiling with current config.

   Ready for the next level? Just say: "Let's try 200 VUs" or "1k updates forced in 1s"
════════════════════════════════════════════════════════════════
`.trim() + '\n',
    };
}