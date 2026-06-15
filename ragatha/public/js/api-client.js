/**
 * CarniHub Admin API Client v1.0
 * 
 * Wrapper global para consumir la Admin API con JWT Bearer.
 * 
 * === USO ===
 * 
 * // Login
 * const resp = await ApiClient.login('admin@amare.com', 'password');
 * if (resp.success) {
 *   // token se guarda automáticamente en localStorage
 *   // redirigir al dashboard
 * }
 * 
 * // Peticiones autenticadas (el token se envía automáticamente)
 * const users = await ApiClient.get('/admin/users');
 * const promos = await ApiClient.get('/admin/promotions?page=1');
 * const result = await ApiClient.post('/admin/promotions', { usuario_id: 1, titulo: 'Oferta' });
 * const updated = await ApiClient.put('/admin/promotions/5', { titulo: 'Nuevo título' });
 * await ApiClient.del('/admin/promotions/5');
 * 
 * // Respuesta estándar: { success: true|false, message: "...", data: {...} }
 * // En caso de error 422: { success: false, message: "...", errors: { campo: ["mensaje"] } }
 */

(function () {
  'use strict';

  const STORAGE_KEY = 'carnihub_admin_token';
  const STORAGE_USER = 'carnihub_admin_user';

  const BASE_URL = (function () {
    // Usar variable global inyectada por PHP si existe (más confiable)
    if (typeof window.CARNIHUB_BASE_URL !== 'undefined' && window.CARNIHUB_BASE_URL) {
      var u = window.CARNIHUB_BASE_URL;
      if (u.substr(-1) !== '/') u += '/';
      // IMPORTANTE: Limpiar prefijos de módulos que PHP incluye en BASE_URL
      // Esto asegura que las llamadas a /api/admin/... se resuelvan correctamente
      u = u.replace(/\/restaurante\/+$/, '/')
            .replace(/\/rest-promocion\/+$/, '/')
            .replace(/\/rest-config\/+$/, '/')
            .replace(/\/rest-menu\/+$/, '/')
            .replace(/\/rest-.*\/+$/, '/')
            .replace(/\/auth\/+$/, '/');
      return u;
    }
    // Fallback: auto-detectar la URL base de la app
    var path = window.location.pathname;
    var base = path.replace(/\/restaurante\/.*$/, '/')
                   .replace(/\/rest-promocion\/.*$/, '/')
                   .replace(/\/rest-config\/.*$/, '/')
                   .replace(/\/rest-menu\/.*$/, '/')
                   .replace(/\/rest-.*\/.*$/, '/')
                   .replace(/\/auth\/.*$/, '/');
    if (base.substr(-1) !== '/') base += '/';
    return base;
  })();

  window.ApiClient = {
    /**
     * Obtiene el token guardado
     */
    getToken: function () {
      return localStorage.getItem(STORAGE_KEY);
    },

    /**
     * Obtiene el usuario guardado
     */
    getUser: function () {
      var raw = localStorage.getItem(STORAGE_USER);
      return raw ? JSON.parse(raw) : null;
    },

    /**
     * Verifica si hay sesión activa
     */
    isLoggedIn: function () {
      return !!this.getToken();
    },

    /**
     * Cierra sesión (limpia storage)
     */
    logout: function () {
      localStorage.removeItem(STORAGE_KEY);
      localStorage.removeItem(STORAGE_USER);
    },

    /**
     * GET /api/auth/token.php — Obtener JWT si ya tienes sesión PHP
     * Uso: Después de login en /restaurante/, llama esto para obtener JWT
     * @returns {Promise<boolean>} true si obtuvo token, false si no
     */
    getTokenFromSession: async function() {
      try {
        var resp = await fetch(BASE_URL + 'api/auth/token.php', {
          method: 'GET',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include'  // Enviar cookies de sesión PHP
        });
        var data = await resp.json();

        if (data.success && data.data && data.data.token) {
          localStorage.setItem(STORAGE_KEY, data.data.token);
          localStorage.setItem(STORAGE_USER, JSON.stringify(data.data.user));
          return true;
        }
        return false;
      } catch (err) {
        console.error('Error obteniendo token:', err.message);
        return false;
      }
    },

    /**
     * POST /api/auth/login
     * @param {string} email
     * @param {string} password
     * @returns {Promise<{success: boolean, message: string, data?: {user: object, token: string}}>}
     */
    login: async function (email, password) {
      var resp = await fetch(BASE_URL + 'api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: email, password: password }),
      });
      var data = await resp.json();

      if (data.success && data.data && data.data.token) {
        // Guardar token y usuario automáticamente
        localStorage.setItem(STORAGE_KEY, data.data.token);
        localStorage.setItem(STORAGE_USER, JSON.stringify(data.data.user));
      }

      return data;
    },

    /**
     * GET request autenticado
     * @param {string} endpoint — ej. '/admin/users' o '/admin/promotions?page=1'
     * @returns {Promise<{success: boolean, message: string, data?: any, errors?: object}>}
     */
    get: async function (endpoint) {
      return this._request('GET', endpoint);
    },

    /**
     * POST request autenticado
     * @param {string} endpoint
     * @param {object} body
     * @returns {Promise<{success: boolean, message: string, data?: any, errors?: object}>}
     */
    post: async function (endpoint, body) {
      return this._request('POST', endpoint, body);
    },

    /**
     * PUT request autenticado
     * @param {string} endpoint
     * @param {object} body
     * @returns {Promise<{success: boolean, message: string, data?: any, errors?: object}>}
     */
    put: async function (endpoint, body) {
      return this._request('PUT', endpoint, body);
    },

    /**
     * DELETE request autenticado
     * @param {string} endpoint
     * @returns {Promise<{success: boolean, message: string, data?: any, errors?: object}>}
     */
    del: async function (endpoint) {
      return this._request('DELETE', endpoint);
    },

    /**
     * Método interno: añade Authorization header y parsea respuesta
     */
    _request: async function (method, endpoint, body) {
      var token = this.getToken();
      var headers = {};

      if (!(body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
      }

      if (token) {
        headers['Authorization'] = 'Bearer ' + token;
      }

      var opts = {
        method: method,
        headers: headers,
        credentials: 'include'
      };

      if (body && (method === 'POST' || method === 'PUT')) {

        if (body instanceof FormData) {
          opts.body = body;
        } else {
          opts.body = JSON.stringify(body);
        }

      }

      // Usar /api_restaurante/ que ya funciona en el servidor (mod_rewrite no está habilitado)
      // Endpoints como /admin/promotions se construirán como /api_restaurante/admin/promotions
      var url = endpoint;
      if (url.indexOf('api_restaurante/') !== 0 && url.indexOf('/api_restaurante/') !== 0) {
        url = BASE_URL + 'api_restaurante' + (url.substr(0,1) === '/' ? url : '/' + url);
      } else if (url.indexOf('/api_restaurante/') === 0) {
        url = BASE_URL + url.substr(1);
      } else if (url.indexOf('api_restaurante/') === 0) {
        url = BASE_URL + url;
      }

      try {
        var resp = await fetch(url, opts);
        var data = await resp.json();

        // Si el token expiró (401), limpiar sesión
        if (resp.status === 401 && this.isLoggedIn()) {
          this.logout();
          // Redirigir al login si no estamos ya en la página de login
          if (window.location.pathname.indexOf('/auth/login') === -1) {
            window.location.href = BASE_URL + 'auth/login';
          }
        }

        return data;
      } catch (err) {
        return { success: false, message: 'Error de conexión: ' + err.message };
      }
    },

    /**
     * Muestra errores de validación (422) en un contenedor.
     * @param {object} errorData — { success: false, message: "...", errors: { campo: ["msg"] } }
     * @param {string} containerSelector — selector CSS del contenedor donde mostrar errores
     */
    showErrors: function (errorData, containerSelector) {
      var container = document.querySelector(containerSelector);
      if (!container) return;

      if (!errorData || !errorData.errors) {
        container.innerHTML = '<div class="api-error">' + (errorData ? this._esc(errorData.message) : 'Error desconocido') + '</div>';
        return;
      }

      var html = '';
      for (var field in errorData.errors) {
        if (errorData.errors.hasOwnProperty(field)) {
          var msgs = errorData.errors[field];
          if (Array.isArray(msgs)) {
            for (var i = 0; i < msgs.length; i++) {
              html += '<div class="api-error">' + this._esc(msgs[i]) + '</div>';
            }
          } else if (typeof msgs === 'string') {
            html += '<div class="api-error">' + this._esc(msgs) + '</div>';
          }
        }
      }

      container.innerHTML = html;
      container.style.display = 'block';
    },

    /**
     * Muestra un mensaje flash de éxito o error usando el contenedor de flash de la app
     */
    flash: function (type, message) {
      var flashDiv = document.createElement('div');
      flashDiv.className = 'flash flash-' + (type === 'success' ? 'success' : 'error');
      flashDiv.textContent = message;
      flashDiv.onclick = function () { flashDiv.remove(); };

      // Insertar al inicio de .rst-page o .page-content
      var container = document.querySelector('.rst-page') || document.querySelector('.page-content') || document.body;
      if (container.firstChild) {
        container.insertBefore(flashDiv, container.firstChild);
      } else {
        container.appendChild(flashDiv);
      }

      // Auto-remover después de 5 segundos
      setTimeout(function () { flashDiv.remove(); }, 5000);
    },

    _esc: function (str) {
      var div = document.createElement('div');
      div.appendChild(document.createTextNode(str));
      return div.innerHTML;
    }
  };
})();