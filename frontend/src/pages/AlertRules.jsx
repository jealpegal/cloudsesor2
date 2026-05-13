import { useState, useEffect } from 'react'
import { useParams, Link } from 'react-router-dom'
import { api } from '../api/client'
import Card from '../components/Card'
import Message from '../components/Message'
import PageHeader from '../components/PageHeader'

/**
 * Reglas de alerta del sensor: crear y listar (variable operador umbral).
 */
export default function AlertRules() {
  const { sensorId } = useParams()
  const [sensor, setSensor] = useState(null)
  const [variables, setVariables] = useState([])
  const [rules, setRules] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [success, setSuccess] = useState(null)
  const [showForm, setShowForm] = useState(false)
  const [variableId, setVariableId] = useState('')
  const [operator, setOperator] = useState('>')
  const [thresholdValue, setThresholdValue] = useState('')
  const [description, setDescription] = useState('')
  const [submitting, setSubmitting] = useState(false)

  const load = () => {
    Promise.all([
      api.getSensor(sensorId),
      api.getVariables(sensorId),
      api.getAlertRules(sensorId),
    ])
      .then(([s, v, r]) => {
        setSensor(s)
        setVariables(Array.isArray(v) ? v : [])
        setRules(Array.isArray(r) ? r : [])
      })
      .catch((e) => setError(e.message || 'Error al cargar'))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    load()
  }, [sensorId])

  const handleCreate = (e) => {
    e.preventDefault()
    setError(null)
    setSuccess(null)
    const threshold = parseFloat(thresholdValue)
    if (Number.isNaN(threshold)) {
      setError('El umbral debe ser un número')
      return
    }
    setSubmitting(true)
    api
      .createAlertRule({
        sensor_id: parseInt(sensorId, 10),
        variable_id: parseInt(variableId, 10),
        operator,
        threshold_value: threshold,
        description: description.trim() || null,
      })
      .then(() => {
        setSuccess('Regla de alerta creada correctamente')
        setVariableId('')
        setThresholdValue('')
        setDescription('')
        setShowForm(false)
        load()
      })
      .catch((err) => setError(err.message || 'Error al crear regla'))
      .finally(() => setSubmitting(false))
  }

  const handleDelete = (rule) => {
    if (!window.confirm(`¿Eliminar regla "${rule.variable_name} ${rule.operator} ${rule.threshold_value}"?`)) return
    setError(null)
    api
      .deleteAlertRule(rule.id)
      .then(() => {
        setSuccess('Regla eliminada')
        load()
      })
      .catch((e) => setError(e.message || 'Error al eliminar'))
  }

  if (loading) return <p className="loading">Cargando reglas…</p>
  if (!sensor) return null

  return (
    <Card>
      <PageHeader
        title={`Reglas de alerta — ${sensor.name}`}
        breadcrumb={[
          { label: 'Dashboard', to: '/' },
          { label: sensor.name, to: `/sensors/${sensorId}` },
          { label: 'Reglas de alerta' },
        ]}
      />
      {error && <Message type="error" onDismiss={() => setError(null)}>{error}</Message>}
      {success && <Message type="success" onDismiss={() => setSuccess(null)}>{success}</Message>}
      <p className="text-muted small" style={{ marginBottom: '1rem' }}>
        Cuando una variable cumpla la condición, se registrará una alerta en <Link to="/alerts">Alertas</Link>.
      </p>
      {!showForm ? (
        <button type="button" className="btn btn-primary" onClick={() => setShowForm(true)}>
          Añadir regla de alerta
        </button>
      ) : (
        <form onSubmit={handleCreate} className="form-inline">
          <div className="form-group">
            <label>Variable *</label>
            <select value={variableId} onChange={(e) => setVariableId(e.target.value)} required>
              <option value="">Seleccionar</option>
              {variables.map((v) => (
                <option key={v.id} value={v.id}>{v.name}</option>
              ))}
            </select>
          </div>
          <div className="form-group">
            <label>Operador *</label>
            <select value={operator} onChange={(e) => setOperator(e.target.value)}>
              <option value=">">&gt;</option>
              <option value="<">&lt;</option>
              <option value=">=">≥</option>
              <option value="<=">≤</option>
              <option value="=">=</option>
            </select>
          </div>
          <div className="form-group">
            <label>Valor umbral *</label>
            <input
              type="number"
              step="any"
              value={thresholdValue}
              onChange={(e) => setThresholdValue(e.target.value)}
              required
            />
          </div>
          <div className="form-group">
            <label>Descripción</label>
            <input
              type="text"
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="Opcional"
            />
          </div>
          <button type="submit" className="btn btn-primary" disabled={submitting}>
            {submitting ? 'Creando…' : 'Crear'}
          </button>
          <button
            type="button"
            className="btn btn-secondary"
            style={{ marginLeft: '0.5rem' }}
            onClick={() => setShowForm(false)}
          >
            Cancelar
          </button>
        </form>
      )}
      <div className="table-wrap" style={{ marginTop: '1rem' }}>
        <table>
          <thead>
            <tr>
              <th>Variable</th>
              <th>Condición</th>
              <th>Descripción</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            {rules.length === 0 ? (
              <tr>
                <td colSpan={4}>No hay reglas. Crea una para que se disparen alertas cuando lleguen datos.</td>
              </tr>
            ) : (
              rules.map((r) => (
                <tr key={r.id}>
                  <td>{r.variable_name}</td>
                  <td>{r.operator} {r.threshold_value}</td>
                  <td>{r.description || '—'}</td>
                  <td>
                    <button type="button" className="btn btn-danger btn-sm" onClick={() => handleDelete(r)}>
                      Eliminar
                    </button>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </Card>
  )
}
