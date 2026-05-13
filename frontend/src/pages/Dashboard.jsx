import { useState, useEffect, useCallback } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api/client'
import Card from '../components/Card'
import Message from '../components/Message'

const ALERTS_POLL_INTERVAL_MS = 5000
const MEASUREMENTS_PER_SENSOR = 5

/**
 * Dashboard: sensores, últimas mediciones por sensor y alertas en tiempo real (polling cada 5 s).
 */
export default function Dashboard() {
  const [sensors, setSensors] = useState([])
  const [measurementsBySensor, setMeasurementsBySensor] = useState({})
  const [alerts, setAlerts] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [success, setSuccess] = useState(null)

  const loadSensors = useCallback(() => {
    return api.getSensors(true).then((data) => {
      setSensors(Array.isArray(data) ? data : [])
      return Array.isArray(data) ? data : []
    })
  }, [])

  const loadMeasurements = useCallback((sensorList) => {
    const list = Array.isArray(sensorList) ? sensorList : []
    if (!list.length) {
      setMeasurementsBySensor({})
      return
    }
    Promise.all(
      list.map((s) =>
        api.getMeasurements(s.id, MEASUREMENTS_PER_SENSOR).then((data) => ({ id: s.id, data }))
      )
    ).then((results) => {
      const byId = {}
      results.forEach(({ id, data }) => {
        byId[id] = data
      })
      setMeasurementsBySensor(byId)
    })
  }, [])

  const loadAlerts = useCallback(() => {
    api.getAlerts({ limit: 20 })
      .then((data) => setAlerts(Array.isArray(data) ? data : []))
      .catch(() => {})
  }, [])

  useEffect(() => {
    setLoading(true)
    setError(null)
    loadSensors()
      .then((list) => {
        loadMeasurements(list)
      })
      .catch((e) => setError(e.message || 'Error al cargar sensores'))
      .finally(() => setLoading(false))
  }, [loadSensors, loadMeasurements])

  useEffect(() => {
    loadAlerts()
    const interval = setInterval(loadAlerts, ALERTS_POLL_INTERVAL_MS)
    return () => clearInterval(interval)
  }, [loadAlerts])

  const handleDelete = (id, name) => {
    if (!window.confirm(`¿Eliminar el sensor "${name}"? Se borrarán sus variables, fórmulas y mediciones.`)) return
    setError(null)
    setSuccess(null)
    api
      .deleteSensor(id)
      .then(() => {
        setSensors((prev) => prev.filter((s) => s.id !== id))
        setMeasurementsBySensor((prev) => {
          const next = { ...prev }
          delete next[id]
          return next
        })
        setSuccess('Sensor eliminado correctamente')
      })
      .catch((e) => setError(e.message || 'Error al eliminar'))
  }

  if (loading) return <p className="loading">Cargando…</p>

  return (
    <div className="dashboard">
      {error && <Message type="error" onDismiss={() => setError(null)}>{error}</Message>}
      {success && <Message type="success" onDismiss={() => setSuccess(null)}>{success}</Message>}

      <Card title="Sensores">
        <ul className="list-sensors">
          {sensors.length === 0 ? (
            <li>
              No hay sensores. <Link to="/sensors/new">Crear el primero</Link>.
            </li>
          ) : (
            sensors.map((s) => (
              <li key={s.id}>
                <div>
                  <Link to={`/sensors/${s.id}`}>
                    <strong>{s.name}</strong>
                  </Link>
                  {s.description && (
                    <span className="text-muted">{s.description}</span>
                  )}
                  <div className="sensor-links">
                    <Link to={`/sensors/${s.id}/variables`}>Variables</Link>
                    {' · '}
                    <Link to={`/sensors/${s.id}/charts`}>Gráficas</Link>
                    {' · '}
                    <Link to={`/sensors/${s.id}/formulas`}>Fórmulas</Link>
                    {' · '}
                    <Link to={`/sensors/${s.id}/alert-rules`}>Reglas de alerta</Link>
                  </div>
                </div>
                <div className="actions">
                  <Link to={`/sensors/${s.id}`} className="btn btn-secondary btn-sm">Editar</Link>
                  <button
                    type="button"
                    className="btn btn-danger btn-sm"
                    onClick={() => handleDelete(s.id, s.name)}
                  >
                    Eliminar
                  </button>
                </div>
              </li>
            ))
          )}
        </ul>
      </Card>

      {sensors.length > 0 && (
        <Card title="Últimas mediciones">
          <div className="measurements-grid">
            {sensors.map((s) => {
              const measurements = Array.isArray(measurementsBySensor[s.id]) ? measurementsBySensor[s.id] : []
              return (
                <div key={s.id} className="measurements-block">
                  <h3 className="measurements-sensor-name">
                    <Link to={`/sensors/${s.id}`}>{s.name}</Link>
                  </h3>
                  {measurements.length === 0 ? (
                    <p className="text-muted small">Sin mediciones recientes</p>
                  ) : (
                    <ul className="measurements-list">
                      {measurements.slice(0, MEASUREMENTS_PER_SENSOR).map((m) => (
                        <li key={m.id}>
                          <span className="var-name">{m.variable_name}</span>
                          <span className="var-value">{Number(m.value).toFixed(2)}</span>
                          <span className="var-time">{new Date(m.measured_at).toLocaleString()}</span>
                        </li>
                      ))}
                    </ul>
                  )}
                </div>
              )
            })}
          </div>
        </Card>
      )}

      <Card title="Alertas en tiempo real">
        <p className="text-muted small">Actualización cada {ALERTS_POLL_INTERVAL_MS / 1000} s</p>
        {alerts.length === 0 ? (
          <p>No hay alertas recientes.</p>
        ) : (
          <div className="alerts-list">
            {alerts.map((a) => (
              <div key={a.id} className={`alert-item ${a.read_at ? 'read' : ''}`}>
                <div className="variable-name">
                  {a.sensor_name} — {a.variable_name}: {a.value} {a.operator} {a.threshold_value}
                </div>
                {a.message && <div>{a.message}</div>}
                <div className="time">{new Date(a.triggered_at).toLocaleString()}</div>
              </div>
            ))}
          </div>
        )}
        <Link to="/alerts" className="btn btn-secondary btn-sm" style={{ marginTop: '0.5rem' }}>
          Ver todas las alertas
        </Link>
      </Card>
    </div>
  )
}
