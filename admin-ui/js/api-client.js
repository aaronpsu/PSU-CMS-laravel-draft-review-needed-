/* Small JSON client for the local Laravel prototype API. */
window.CMS = window.CMS || {};

CMS.api = (function () {
  var csrfToken = '';

  function ensureCsrf() {
    if (csrfToken) return Promise.resolve(csrfToken);
    return fetch(CMS.config.apiBase + '/auth/csrf', {
      credentials: 'include',
      headers: { Accept: 'application/json' }
    }).then(function (response) {
      if (!response.ok) throw new Error('Unable to start a secure session.');
      return response.json();
    }).then(function (body) {
      csrfToken = body.csrfToken || '';
      return csrfToken;
    });
  }

  function request(path, options) {
    options = options || {};
    var headers = Object.assign({ Accept: 'application/json' }, options.headers || {});
    if (options.body && !(options.body instanceof FormData)) headers['Content-Type'] = 'application/json';
    var mutating = ['POST', 'PUT', 'PATCH', 'DELETE'].indexOf((options.method || 'GET').toUpperCase()) !== -1;
    var send = function () {
      if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken;
      return fetch(CMS.config.apiBase + path, Object.assign({}, options, {
        credentials: 'include',
        headers: headers,
        body: options.body && !(options.body instanceof FormData) ? JSON.stringify(options.body) : options.body
      }));
    };
    return (mutating ? ensureCsrf().then(send) : send()).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (body) {
        if (!response.ok) {
          var message = body.message || 'The server could not complete this request.';
          if (body.errors) message = Object.keys(body.errors).map(function (key) { return body.errors[key][0]; }).join(' ');
          var error = new Error(message);
          error.status = response.status;
          throw error;
        }
        if (typeof body.csrfToken === 'string') csrfToken = body.csrfToken;
        return body;
      });
    });
  }

  function query(path, params) {
    var search = new URLSearchParams();
    Object.keys(params || {}).forEach(function (key) {
      if (params[key] !== '' && params[key] !== null && params[key] !== undefined) search.set(key, params[key]);
    });
    return request(path + (search.toString() ? '?' + search.toString() : ''));
  }

  return {
    csrf: ensureCsrf,
    clearCsrf: function () { csrfToken = ''; },
    request: request,
    query: query,
    get: function (path) { return request(path); },
    post: function (path, body) { return request(path, { method: 'POST', body: body }); },
    put: function (path, body) { return request(path, { method: 'PUT', body: body }); },
    remove: function (path) { return request(path, { method: 'DELETE', body: {} }); }
  };
})();
