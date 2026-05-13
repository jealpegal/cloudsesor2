import { useState, useEffect } from 'react'
import { useParams, Link } from 'react-router-dom'
import { api } from '../api/client'
import Card from '../components/Card'
import Message from '../components/Message'
import PageHeader from '../components/PageHeader'

/**
 * Fórmulas del sensor: crear y listar. Expresión tipo "nivel*a1 + temperatura*a2 + b".
 */
export default function Formulas() {
  const { sensorId } = useParams()
  const [sensor, setSensor] = useState(null)
  const [variables, setVariables] = useState([])
  const [formulas, setFormulas] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [success, setSuccess] = useState(null)
  const [showForm, setShowForm] = useState(false)
  const [name, setName] = useState('')
  const [expression, setExpression] = useState('')
  const [resultVariableId, setResultVariableId] = useState('')
  const [parameters, setParameters] = useState('')
  const [submitting, setSubmitting] = useState(false)

  const load = () => {
    Promise.all([
      api.getSensor(sensorId),
      api.getVariables(sensorId),
      api.getFormulas(sensorId),
    ])
      .then(([s, v, f]) => {
        setSensor(s)
        setVariables(Array.isArray(v) ? v : [])
        setFormulas(Array.isArray(f) ? f : [])
      })
      .catch((e) => setError(e.message || 'Error al cargar'))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    load()
  }, [sensorId])

  const calculatedVars = variables.filter((v) => v.type === 'calculated')
  const canCreateFormula = variables.some((v) => v.type === 'calculated')

  const handleCreate = (e) => {
    e.preventDefault()
    setError(null)
    setSuccess(null)
    let params = {}
    try {
      params = JSON.parse(parameters.trim() || '{}')
    } catch (_) {
      setError('Parámetros deben ser JSON válido, ej: {"a1": 1, "a2": 0.5, "b": 0}')
      return
    }
    setSubmitting(true)
    api
      .createFormula({
        sensor_id: parseInt(sensorId, 10),
        name: name.trim(),
        expression: expression.trim(),
        result_variable_id: parseInt(resultVariableId, 10),
        parameters: params,
      })
      .then(() => {
        setSuccess('Fórmula creada correctamente')
        setName('')
        setExpression('')
        setResultVariableId('')
        setParameters('')
        setShowForm(false)
        load()
      })
      .catch((e) => setError(e.message || 'Error al crear fórmula'))
      .finally(() => setSubmitting(false))
  }

  const handleDelete = (f) => {
    if (!window.confirm(`¿Eliminar la fórmula "${f.name}"?`)) return
    setError(null)
    api
      .deleteFormula(f.id)
      .then(() => {
        setSuccess('Fórmula eliminada')
        load()
      })
      .catch((e) => setError(e.message || 'Error al eliminar'))
  }

  if (loading) return <p className="loading">Cargando fórmulas…</p>
  if (!sensor) return null

  return (
    <Card>
      <PageHeader
        title={`Fórmulas — ${sensor.name}`}
        breadcrumb={[
          { label: 'Dashboard', to: '/' },
          { label: sensor.name, to: `/sensors/${sensorId}` },
          { label: 'Fórmulas' },
        ]}
      />
      {error && <Message type="error" onDismiss={() => setError(null)}>{error}</Message>}
      {success && <Message type="success" onDismiss={() => setSuccess(null)}>{success}</Message>}
      <p className="text-muted small" style={{ marginBottom: '1rem' }}>
        Ejemplo: <code>nivel*a1 + temperatura*a2 + b</code>. Los parámetros se definen en JSON.
      </p>
      {!canCreateFormula && (
        <Message type="error">
          Crea al menos una variable de tipo &quot;Calculada&quot; en{' '}
          <Link to={`/sensors/${sensorId}/variables`}>Variables</Link> para usarla como resultado.
        </Message>
      )}
      {!showForm ? (
        <button
          type="button"
          className="btn btn-primary"
          onClick={() => setShowForm(true)}
          disabled={!canCreateFormula}
        >
          Añadir fórmula
        </button>
      ) : (
        <form onSubmit={handleCreate} className="form-inline">
          <div className="form-group">
            <label>Nombre de la fórmula *</label>
            <input
              type="text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="Ej: Cálculo de grasas"
              required
            />
          </div>
          <div className="form-group">
            <label>Expresión *</label>
            <input
              type="text"
              value={expression}
              onChange={(e) => setExpression(e.target.value)}
              placeholder="nivel*a1 + temperatura*a2 + b"
              required
            />
          </div>
          <div className="form-group">
            <label>Variable resultado (calculada) *</label>
            <select
              value={resultVariableId}
              onChange={(e) => setResultVariableId(e.target.value)}
              required
            >
              <option value="">Seleccionar</option>
              {calculatedVars.map((v) => (
                <option key={v.id} value={v.id}>{v.name}</option>
              ))}
            </select>
          </div>
          <div className="form-group">
            <label>Parámetros (JSON)</label>
            <input
              type="text"
              value={parameters}
              onChange={(e) => setParameters(e.target.value)}
              placeholder='Ej: {"a1": 0.5, "a2": 0.2, "b": 0}  (coeficientes de la expresión)'
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
              <th>Nombre</th>
              <th>Expresión</th>
              <th>Resultado</th>
              <th>Parámetros</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            {formulas.length === 0 ? (
              <tr>
                <td colSpan={5}>No hay fórmulas definidas.</td>
              </tr>
            ) : (
              formulas.map((f) => (
                <tr key={f.id}>
                  <td>{f.name}</td>
                  <td><code className="code-sm">{f.expression}</code></td>
                  <td>{f.result_variable_name}</td>
                  <td><code className="code-sm">{JSON.stringify(f.parameters)}</code></td>
                  <td>
                    <button type="button" className="btn btn-danger btn-sm" onClick={() => handleDelete(f)}>
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
