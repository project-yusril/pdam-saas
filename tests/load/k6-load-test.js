// k6 load test — PDAM SaaS API (parameterized; runner: ops/load/run_load_test.sh)
//
//   PROFILE=smoke|load|stress  BASE_URL=https://api.pdam.go.id/api/v1
//   PDAM_CODE=<tenant uji> LOGIN_EMAIL=<service acct> LOGIN_PASSWORD=<...>
//
// Hasilkan capacity baseline: attach p95 + error rate ke ops/runbooks/OBSERVABILITY.md
import http from 'k6/http';
import { check, sleep } from 'k6';

const PROFILE = __ENV.PROFILE || 'load';
const BASE_URL = __ENV.BASE_URL || 'http://localhost:8080/api/v1';

const PROFILES = {
  smoke: { stages: [{ duration: '20s', target: 5 }], thresholds: { http_req_duration: ['p(95)<800'], 'http_req_failed': ['rate<0.05'] } },
  load: {
    stages: [
      { duration: '30s', target: 10 },
      { duration: '2m', target: 50 },
      { duration: '30s', target: 0 },
    ],
    thresholds: {
      http_req_duration: ['p(95)<500'],
      'http_req_failed': ['rate<0.02'],
      'http_req_duration{expectedResponseTime:login}': ['p(95)<300'],
      'http_req_duration{expectedResponseTime:list}': ['p(95)<600'],
    },
  },
  stress: {
    stages: [
      { duration: '1m', target: 100 },
      { duration: '2m', target: 200 },
      { duration: '1m', target: 0 },
    ],
    thresholds: { 'http_req_failed': ['rate<0.15'], http_req_duration: ['p(95)<1500'] },
  },
};

const cfg = PROFILES[PROFILE] || PROFILES.load;

export const options = { stages: cfg.stages, thresholds: cfg.thresholds };

function login() {
  if (!__ENV.LOGIN_EMAIL || !__ENV.LOGIN_PASSWORD) {
    throw new Error('LOGIN_EMAIL/LOGIN_PASSWORD environment wajib (akun service uji — tidak lagi hardcode)');
  }
  const res = http.post(`${BASE_URL}/login`, JSON.stringify({
    email: __ENV.LOGIN_EMAIL,
    password: __ENV.LOGIN_PASSWORD,
    pdam_code: __ENV.PDAM_CODE,
    device_name: 'k6',
  }), { headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, tags: { expectedResponseTime: 'login' } });
  check(res, { 'login 200': (r) => r.status === 200 });
  const token = res.json('data.token') || res.json('token');
  if (!token) {
    throw new Error('login tanpa token: HTTP ' + res.status);
  }
  return token;
}

export function setup() {
  return { token: login() };
}

function get(path, tag, data) {
  const res = http.get(`${BASE_URL}${path}${data ? '?' + data : ''}`, {
    headers: { Authorization: `Bearer ${__sharedToken}`, Accept: 'application/json' },
    tags: { expectedResponseTime: tag },
  });
  check(res, { [`${path} 200`]: (r) => r.status === 200 });
}

export default function (data) {
  __sharedToken = data.token;
  get('/customers', 'list', 'per_page=20');
  get('/dashboard/director', 'kpi');
  get('/dashboard/finance', 'kpi');
  get('/gis/pipes', 'list', 'per_page=20');
  get('/hr/employees', 'list');
  get('/bills', 'list', 'per_page=20');
  get('/complaints', 'list');
  get('/work-orders', 'list');
  sleep(1);
}
