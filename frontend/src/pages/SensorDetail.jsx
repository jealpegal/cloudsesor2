import { useState, useEffect } from 'react'
import { useParams, Link } from 'react-router-dom'
import { api } from '../api/client'
import Card from '../components/Card'
import Message from '../components/Message'
import PageHeader from '../components/PageHeader'

/**
 * Detalle y edición de un sensor.
 */
export default function SensorDetail() {
  const { sensorId } = useParams()
  const [sensor, setSensor] = useState(null)
  const [name, setName] = useState('')
  const [description, setDescription] = useState('')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState(null)
  const [success, setSuccess] = useState(null)

  useEffect(() => {
    api
      .getSensor(sensorId)
      .then((data) => {
        setSensor(data)
        setName(data.name)
        setDescription(data.description || '')
      })
      .catch((e) => setError(e.message || 'Sensor no encontrado'))
      .finally(() => setLoading(false))
  }, [sensorId])

  const handleSubmit = (e) => {
    e.preventDefault()
    setSaving(true)
    setError(null)
    setSuccess(null)
    api
      .updateSensor(sensorId, { name: name.trim(), description: description.trim() || null })
      .then((updated) => {
        setSensor(updated)
        setSuccess('Cambios guardados correctamente')
      })
      .catch((e) => setError(e.message || 'Error al guardar'))
      .finally(() => setSaving(false))
  }

  if (loading) return <p className="loading">Cargando…</p>
  if (!sensor && error) return <Message type="error">{error}</Message>
  if (!sensor) return null

  return (
    <Card title="Editar sensor">
      <PageHeader
        breadcrumb={[
          { label: 'Dashboard', to: '/' },
          { label: sensor.name },
        ]}
      />
      {error && <Message type="error" onDismiss={() => setError(null)}>{error}</Message>}
      {success && <Message type="success" onDismiss={() => setSuccess(null)}>{success}</Message>}
      <form onSubmit={handleSubmit}>
        <div className="form-group">
          <label htmlFor="name">Nombre *</label>
          <input
            id="name"
            type="text"
            value={name}
            onChange={(e) => setName(e.target.value)}
            required
          />
        </div>
        <div className="form-group">
          <label htmlFor="description">Descripción</label>
          <textarea
            id="description"
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            rows={2}
          />
        </div>
        <button type="submit" className="btn btn-primary" disabled={saving}>
          {saving ? 'Guardando…' : 'Guardar'}
        </button>
        <Link to={`/sensors/${sensorId}/variables`} className="btn btn-secondary" style={{ marginLeft: '0.5rem' }}>
          Variables
        </Link>
        <Link to={`/sensors/${sensorId}/charts`} className="btn btn-secondary" style={{ marginLeft: '0.5rem' }}>
          Gráficas
        </Link>
        <Link to="/" className="btn btn-secondary" style={{ marginLeft: '0.5rem' }}>
          Volver
        </Link>
      </form>
    </Card>
  )
}
