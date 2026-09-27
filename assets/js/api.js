// Setlo API client: JSON fetch wrapper with CSRF header and session-expiry handling.
(function (global) {
  const meta = (name) => document.querySelector('meta[name="' + name + '"]')?.content || '';
  const apiBase = meta('api-base');

  class ApiError extends Error {
    constructor(message, status, data) {
      super(message);
      this.status = status;
      this.data = data || {};
    }
  }

  async function request(method, path, data) {
    const opts = { method, headers: { Accept: 'application/json' }, credentials: 'same-origin' };
    if (method !== 'GET') {
      opts.headers['X-CSRF-Token'] = meta('csrf-token');
      if (data instanceof FormData) {
        opts.body = data;
      } else {
        opts.headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(data || {});
      }
    }

    let res, json;
    try {
      res = await fetch(apiBase + path, opts);
      json = await res.json();
    } catch (e) {
      throw new ApiError('Could not reach the server. Check your connection.', 0);
    }

    if (res.status === 401) {
      location.href = meta('login-page') || 'login.php';
      throw new ApiError(json.error, 401, json);
    }
    if (!res.ok || !json.ok) {
      throw new ApiError(json.error || 'Request failed.', res.status, json);
    }
    return json;
  }

  global.api = {
    ApiError,
    get: (path, params) => {
      const qs = params ? '?' + new URLSearchParams(params).toString() : '';
      return request('GET', path + qs);
    },
    post: (path, data) => request('POST', path, data),
    /** Update the CSRF token after login/register rotates the session. */
    setCsrf: (token) => {
      const el = document.querySelector('meta[name="csrf-token"]');
      if (el && token) el.content = token;
    },
  };
})(window);
