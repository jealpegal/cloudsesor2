import { useState, useEffect, useCallback } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api/client'
import Card from '../components/Card'
import Message from '../components/Message'

const POLL_INTERVAL_MS = 5000

/**
 * Lista de alertas disparadas. Polling cada 5 s para tiempo real.
 */
export default function Alerts() {
  const [alerts, setAlerts] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [success, setSuccess] = useState(null)
  const [unreadOnly, setUnreadOnly] = useState(false)

  const load = useCallback(() => {
    api
      .getAlerts({ unread_only: unreadOnly ? 1 : 0, limit: 100 })
      .then((data) => setAlerts(Array.isArray(data) ? data : []))
      .catch((e) => setError(e.message || 'Error al cargar alertas'))
      .finally(() => setLoading(false))
  }, [unreadOnly])

  useEffect(() => {
    load()
  }, [load])

  useEffect(() => {
    const interval = setInterval(load, POLL_INTERVAL_MS)
    return () => clearInterval(interval)
  }, [load])

  const handleMarkRead = (id) => {
    api
      .markAlertRead(id)
      .then(() => {
        setAlerts((prev) => prev.map((a) => (a.id === id ? { ...a, read_at: new Date().toISOString() } : a)))
        setSuccess('Alerta marcada como leída')
      })
      .catch((e) => setError(e.message || 'Error'))
  }

  if (loading && alerts.length === 0) return <p className="loading">Cargando alertas…</p>

  return (
    <Card title="Alertas">
      {error && <Message type="error" onDismiss={() => setError(null)}>{error}</Message>}
      {success && <Message type="success" onDismiss={() => setSuccess(null)}>{success}</Message>}
      <p className="text-muted small" style={{ marginBottom: '1rem' }}>
        Las alertas se generan cuando una variable supera el umbral definido en las reglas. Actualización cada {POLL_INTERVAL_MS / 1000} s.
      </p>
      <label className="checkbox-label">
        <input
          type="checkbox"
          checked={unreadOnly}
          onChange={(e) => setUnreadOnly(e.target.checked)}
        />
        Solo no leídas
      </label>
      <div className="alerts-list" style={{ marginTop: '1rem' }}>
        {alerts.length === 0 ? (
          <p>No hay alertas.</p>
        ) : (
          alerts.map((a) => (
            <div key={a.id} className={`alert-item ${a.read_at ? 'read' : ''}`}>
              <div className="variable-name">
                {a.sensor_name} — {a.variable_name}: {a.value} {a.operator} {a.threshold_value}
              </div>
              {a.message && <div>{a.message}</div>}
              <div className="time">
                {new Date(a.triggered_at).toLocaleString()}
                {!a.read_at && (
                  <button
                    type="button"
                    className="btn btn-secondary btn-sm"
                    style={{ marginLeft: '0.75rem' }}
                    onClick={() => handleMarkRead(a.id)}
                  >
                    Marcar leída
                  </button>
                )}
              </div>
            </div>
          ))
        )}
      </div>
      <p style={{ marginTop: '1rem' }}>
        Configura reglas en <Link to="/">Dashboard</Link> → sensor → Reglas de alerta.
      </p>
    </Card>
  )
}
