import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { api } from '../api/client'
import Card from '../components/Card'
import Message from '../components/Message'

/**
 * Formulario para crear un nuevo sensor.
 */
export default function CreateSensor() {
  const navigate = useNavigate()
  const [name, setName] = useState('')
  const [description, setDescription] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(null)
  const [success, setSuccess] = useState(null)

  const handleSubmit = (e) => {
    e.preventDefault()
    setError(null)
    setSuccess(null)
    setLoading(true)
    api
      .createSensor({ name: name.trim(), description: description.trim() || null })
      .then((sensor) => {
        setSuccess('Sensor creado correctamente. Redirigiendo a variables…')
        setTimeout(() => navigate(`/sensors/${sensor.id}/variables`), 1500)
      })
      .catch((e) => {
        setError(e.message || 'Error al crear sensor')
        setLoading(false)
      })
  }

  return (
    <Card title="Crear sensor">
      {error && <Message type="error" onDismiss={() => setError(null)}>{error}</Message>}
      {success && <Message type="success">{success}</Message>}
      <form onSubmit={handleSubmit}>
        <div className="form-group">
          <label htmlFor="name">Nombre *</label>
          <input
            id="name"
            type="text"
            value={name}
            onChange={(e) => setName(e.target.value)}
            required
            placeholder="Ej: Tanque principal"
          />
        </div>
        <div className="form-group">
          <label htmlFor="description">Descripción</label>
          <textarea
            id="description"
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            rows={2}
            placeholder="Opcional"
          />
        </div>
        <button type="submit" className="btn btn-primary" disabled={loading}>
          {loading ? 'Creando…' : 'Crear sensor'}
        </button>
        <button
          type="button"
          className="btn btn-secondary"
          style={{ marginLeft: '0.5rem' }}
          onClick={() => navigate('/')}
        >
          Cancelar
        </button>
      </form>
    </Card>
  )
}
