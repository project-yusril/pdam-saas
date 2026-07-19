import http from 'k6/http';
import { check, sleep, group } from 'k6';

export const options = {
  stages: [
    { duration: '30s', target: 10 },   // ramp-up
    { duration: '2m', target: 50 },    // peak
    { duration: '30s', target: 0 },    // cool-down
  ],
  thresholds: {
    http_req_duration: ['p(95)<500'],  // 95% requests below 500ms
    'http_req_duration{endpoint:login}': ['p(95)<300'],
    'http_req_duration{endpoint:list}': ['p(95)<600'],
  },
};

const BASE_URL = 'http://localhost:8080/api/v1';
let TOKEN = '';

export function setup() {
  const loginRes = http.post(`${BASE_URL}/login`, JSON.stringify({
    pdam_code: 'PDAM001',
    email: 'admin@pdam.go.id',
    password: 'password',
  }), { headers: { 'Content-Type': 'application/json' } });

  check(loginRes, { 'login ok': (r) => r.status === 200 });
  return { token: loginRes.json('token') };
}

export default function (data) {
  TOKEN = data.token;
  const headers = { Authorization: `Bearer ${TOKEN}`, Accept: 'application/json' };

  group('Customer list', () => {
    const res = http.get(`${BASE_URL}/customers?per_page=25`, { headers, tags: { endpoint: 'list' } });
    check(res, { 'status 200': (r) => r.status === 200 });
  });

  group('Dashboard', () => {
    http.get(`${BASE_URL}/dashboard/director`, { headers });
    http.get(`${BASE_URL}/dashboard/finance`, { headers });
  });

  group('GIS', () => {
    http.get(`${BASE_URL}/gis/customers`, { headers });
    http.get(`${BASE_URL}/gis/customers/status-summary`, { headers });
  });

  group('Employee list', () => {
    http.get(`${BASE_URL}/employees?per_page=25`, { headers, tags: { endpoint: 'list' } });
  });

  group('Work Orders', () => {
    http.get(`${BASE_URL}/work-orders?per_page=25`, { headers, tags: { endpoint: 'list' } });
  });

  group('Bills', () => {
    http.get(`${BASE_URL}/bills?per_page=25`, { headers, tags: { endpoint: 'list' } });
  });

  group('Complaints', () => {
    http.get(`${BASE_URL}/complaints?per_page=25`, { headers, tags: { endpoint: 'list' } });
  });

  sleep(1);
}
