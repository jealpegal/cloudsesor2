/**
 * Cliente API para el backend.
 * Usa VITE_API_URL si está definido; si no, usa /api (proxy de Vite en desarrollo).
 */

const API_BASE = import.meta.env.VITE_API_URL
  ? `${import.meta.env.VITE_API_URL.replace(/\/$/, '')}`
  : '/api';

async function request(path, options = {}) {
  const url = path.startsWith('http') ? path : `${API_BASE}/${path.replace(/^\//, '')}`;
  const config = {
    headers: {
      'Content-Type': 'application/json',
      ...options.headers,
    },
    ...options,
  };
  if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
    config.body = JSON.stringify(options.body);
  }
  const res = await fetch(url, config);
  const text = await res.text();
  let data = null;
  if (text) {
    try {
      data = JSON.parse(text);
    } catch (_) {
      data = { error: text };
    }
  }
  if (!res.ok) {
    const err = new Error(data?.error || data?.message || res.statusText);
    err.status = res.status;
    err.data = data;
    // Mostrar en consola el mensaje del backend (útil para 500, conexión DB, etc.)
    if (data?.message) {
      console.error('[API]', res.status, data.error || 'Error', '-', data.message);
    }
    throw err;
  }
  return data;
}

export const api = {
  getSensors: (withVariables = false) =>
    request(`sensors${withVariables ? '?with_variables=1' : ''}`),
  getSensor: (id) => request(`sensors/${id}`),
  createSensor: (body) => request('sensors', { method: 'POST', body }),
  updateSensor: (id, body) => request(`sensors/${id}`, { method: 'PUT', body }),
  deleteSensor: (id) => request(`sensors/${id}`, { method: 'DELETE' }),

  getVariables: (sensorId) => request(`sensors/${sensorId}/variables`),
  createVariable: (sensorId, body) =>
    request(`sensors/${sensorId}/variables`, { method: 'POST', body }),
  updateVariable: (sensorId, variableId, body) =>
    request(`sensors/${sensorId}/variables/${variableId}`, { method: 'PUT', body }),
  deleteVariable: (sensorId, variableId) =>
    request(`sensors/${sensorId}/variables/${variableId}`, { method: 'DELETE' }),

  getMeasurements: (sensorId, limit = 10, opts = {}) => {
    const params = new URLSearchParams({ limit: String(limit) });
    if (opts.variableId) params.set('variable_id', String(opts.variableId));
    if (opts.chart) params.set('chart', '1');
    return request(`sensors/${sensorId}/measurements?${params}`);
  },

  getFormulas: (sensorId) => request(`sensors/${sensorId}/formulas`),
  createFormula: (body) => request('formulas', { method: 'POST', body }),
  updateFormula: (id, body) => request(`formulas/${id}`, { method: 'PUT', body }),
  deleteFormula: (id) => request(`formulas/${id}`, { method: 'DELETE' }),

  getAlertRules: (sensorId) => request(`sensors/${sensorId}/alert-rules`),
  createAlertRule: (body) => request('alert-rules', { method: 'POST', body }),
  updateAlertRule: (id, body) => request(`alert-rules/${id}`, { method: 'PUT', body }),
  deleteAlertRule: (id) => request(`alert-rules/${id}`, { method: 'DELETE' }),

  getAlerts: (params = {}) => {
    const q = new URLSearchParams(params).toString();
    return request(`alerts${q ? `?${q}` : ''}`);
  },
  markAlertRead: (id) => request(`alerts/${id}/read`, { method: 'POST' }),

  sendData: (body) => request('data', { method: 'POST', body }),
};
