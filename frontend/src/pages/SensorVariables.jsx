import { useState, useEffect } from 'react'
import { useParams, Link } from 'react-router-dom'
import { api } from '../api/client'
import Card from '../components/Card'
import Message from '../components/Message'
import PageHeader from '../components/PageHeader'

/**
 * Lista y creación de variables de un sensor (medidas y calculadas).
 */
export default function SensorVariables() {
  const { sensorId } = useParams()
  const [sensor, setSensor] = useState(null)
  const [variables, setVariables] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [success, setSuccess] = useState(null)
  const [showForm, setShowForm] = useState(false)
  const [newName, setNewName] = useState('')
  const [newType, setNewType] = useState('measure')
  const [newUnit, setNewUnit] = useState('')
  const [submitting, setSubmitting] = useState(false)

  const load = () => {
    Promise.all([api.getSensor(sensorId), api.getVariables(sensorId)])
      .then(([s, v]) => {
        setSensor(s)
        setVariables(Array.isArray(v) ? v : [])
      })
      .catch((e) => setError(e.message || 'Error al cargar'))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    load()
  }, [sensorId])

  const handleCreate = (e) => {
    e.preventDefault()
    if (!newName.trim()) return
    setError(null)
    setSuccess(null)
    setSubmitting(true)
    api
      .createVariable(sensorId, {
        name: newName.trim(),
        type: newType,
        unit: newUnit.trim() || null,
      })
      .then(() => {
        setSuccess('Variable creada correctamente')
        setNewName('')
        setNewUnit('')
        setShowForm(false)
        load()
      })
      .catch((e) => setError(e.message || 'Error al crear variable'))
      .finally(() => setSubmitting(false))
  }

  const handleDelete = (v) => {
    if (!window.confirm(`¿Eliminar la variable "${v.name}"?`)) return
    setError(null)
    api
      .deleteVariable(sensorId, v.id)
      .then(() => {
        setSuccess('Variable eliminada')
        load()
      })
      .catch((e) => setError(e.message || 'Error al eliminar'))
  }

  if (loading) return <p className="loading">Cargando variables…</p>
  if (!sensor) return null

  return (
    <Card title={`Variables — ${sensor.name}`}>
      <PageHeader
        breadcrumb={[
          { label: 'Dashboard', to: '/' },
          { label: sensor.name, to: `/sensors/${sensorId}` },
          { label: 'Variables' },
        ]}
      />
      {error && <Message type="error" onDismiss={() => setError(null)}>{error}</Message>}
      {success && <Message type="success" onDismiss={() => setSuccess(null)}>{success}</Message>}
      {!showForm ? (
        <button type="button" className="btn btn-primary" onClick={() => setShowForm(true)}>
          Añadir variable
        </button>
      ) : (
        <form onSubmit={handleCreate} className="form-inline">
          <div className="form-group">
            <label>Nombre *</label>
            <input
              type="text"
              value={newName}
              onChange={(e) => setNewName(e.target.value)}
              placeholder="Ej: nivel, temperatura"
              required
            />
          </div>
          <div className="form-group">
            <label>Tipo</label>
            <select value={newType} onChange={(e) => setNewType(e.target.value)}>
              <option value="measure">Medida</option>
              <option value="calculated">Calculada</option>
            </select>
          </div>
          <div className="form-group">
            <label>Unidad</label>
            <input
              type="text"
              value={newUnit}
              onChange={(e) => setNewUnit(e.target.value)}
              placeholder="Ej: °C, cm, %"
            />
          </div>
          <button type="submit" className="btn btn-primary" disabled={submitting}>
            {submitting ? 'Creando…' : 'Crear'}
          </button>
          <button type="button" className="btn btn-secondary" style={{ marginLeft: '0.5rem' }} onClick={() => setShowForm(false)}>
            Cancelar
          </button>
        </form>
      )}
      <div className="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Nombre</th>
              <th>Tipo</th>
              <th>Unidad</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            {variables.length === 0 ? (
              <tr>
                <td colSpan={4}>No hay variables. Añade al menos una (medida) para recibir datos del NodeMCU.</td>
              </tr>
            ) : (
              variables.map((v) => (
                <tr key={v.id}>
                  <td>{v.name}</td>
                  <td>{v.type === 'calculated' ? 'Calculada' : 'Medida'}</td>
                  <td>{v.unit || '—'}</td>
                  <td>
                    <button type="button" className="btn btn-danger btn-sm" onClick={() => handleDelete(v)}>
                      Eliminar
                    </button>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
      <p style={{ marginTop: '1rem' }}>
        <Link to={`/sensors/${sensorId}/formulas`}>Ir a Fórmulas</Link>
      </p>
    </Card>
  )
}
