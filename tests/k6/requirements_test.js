import http from 'k6/http';
import { check, sleep } from 'k6';

export let options = {
    stages: [
        { duration: '30s', target: 1000 },
        { duration: '1m', target: 1000 },
    ],
    thresholds: {
        'http_req_duration': ['p(95)<100'],
    },
};

const BASE_URL = __ENV.BASE_URL || 'http://app:8000';

export default function () {
    let playerId = `player_${Math.floor(Math.random() * 10000) + 1}`;
    let score = Math.floor(Math.random() * 2000) + 100;

    let updateRes = http.post(
        `${BASE_URL}/api/players/${playerId}/score`,
        JSON.stringify({ score }),
        {
            headers: { 'Content-Type': 'application/json' },
        }
    );

    check(updateRes, {
        'update score status 200': (r) => r.status === 200,
        'update score < 100ms': (r) => r.timings.duration < 100,
    });

    sleep(0.01);

    let rankRes = http.get(`${BASE_URL}/api/players/${playerId}/rank`);

    check(rankRes, {
        'get rank status 200': (r) => r.status === 200,
        'get rank < 100ms': (r) => r.timings.duration < 100,
        'rank updated immediately': (r) => {
            if (r.status === 200) {
                let data = JSON.parse(r.body);
                return data.score === score && data.rank !== undefined;
            }
            return false;
        },
    });

    let limit = Math.floor(Math.random() * 100) + 1;
    let topRes = http.get(`${BASE_URL}/api/leaderboard/top?limit=${limit}`);

    check(topRes, {
        'get top players status 200': (r) => r.status === 200,
        'get top players < 100ms': (r) => r.timings.duration < 100,
        'top players has correct structure': (r) => {
            if (r.status === 200) {
                let data = JSON.parse(r.body);
                return Array.isArray(data.players) && 
                       data.players.length > 0 &&
                       data.players[0].hasOwnProperty('rank') &&
                       data.players[0].hasOwnProperty('score');
            }
            return false;
        },
    });
}

