/* Small JSON client for the local Laravel prototype API. */
window.CMS = window.CMS || {};

CMS.api = (function () {
  function request(path, options) {
    options = options || {};
    var headers = Object.assign({ Accept: 'application/json' }, options.headers || {});
    if (options.body && !(options.body instanceof FormData)) headers['Content-Type'] = 'application/json';
    return fetch(CMS.config.apiBase + path, Object.assign({}, options, {
      headers: headers,
      body: options.body && !(options.body instanceof FormData) ? JSON.stringify(options.body) : options.body
    })).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (body) {
        if (!response.ok) {
          var message = body.message || 'The server could not complete this request.';
          if (body.errors) message = Object.keys(body.errors).map(function (key) { return body.errors[key][0]; }).join(' ');
          var error = new Error(message);
          error.status = response.status;
          throw error;
        }
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
    request: request,
    query: query,
    get: function (path) { return request(path); },
    post: function (path, body) { return request(path, { method: 'POST', body: body }); },
    put: function (path, body) { return request(path, { method: 'PUT', body: body }); },
    remove: function (path) { return request(path, { method: 'DELETE' }); }
  };
})();
