import { useState, useEffect, useCallback } from 'react'
import { useParams, Link } from 'react-router-dom'
import { api } from '../api/client'
import Card from '../components/Card'
import Message from '../components/Message'
import PageHeader from '../components/PageHeader'
import LineChart from '../components/LineChart'

const CHART_LIMIT = 150

/**
 * Gráficas de mediciones por variable: valor vs tiempo.
 * Los datos se guardan en la tabla measurements al enviar por GET /api/data/ingest.
 */
export default function SensorCharts() {
  const { sensorId } = useParams()
  const [sensor, setSensor] = useState(null)
  const [variables, setVariables] = useState([])
  const [chartData, setChartData] = useState({}) // variableId -> [{ measured_at, value }, ...]
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const loadSensorAndVariables = useCallback(() => {
    return api.getSensor(sensorId).then((data) => {
      setSensor(data)
      setVariables(Array.isArray(data.variables) ? data.variables : [])
      return data
    })
  }, [sensorId])

  const loadChartData = useCallback(() => {
    if (!sensorId) return
    api
      .getMeasurements(sensorId, CHART_LIMIT, { chart: true })
      .then((allMeasurements) => {
        // Agrupar por variable_id y ordenar cada grupo por tiempo (para gráfica valor vs tiempo)
        const byVar = {}
        for (const m of allMeasurements) {
          const vid = m.variable_id
          if (!byVar[vid]) byVar[vid] = []
          byVar[vid].push({ measured_at: m.measured_at, value: m.value })
        }
        Object.keys(byVar).forEach((vid) => {
          byVar[vid].sort((a, b) => new Date(a.measured_at) - new Date(b.measured_at))
        })
        setChartData(byVar)
      })
      .catch(() => setChartData({}))
  }, [sensorId])

  useEffect(() => {
    setLoading(true)
    setError(null)
    loadSensorAndVariables()
      .catch((e) => setError(e.message || 'Error al cargar sensor'))
      .finally(() => setLoading(false))
  }, [loadSensorAndVariables])

  useEffect(() => {
    if (!sensorId || variables.length === 0) return
    loadChartData()
  }, [sensorId, variables.length, loadChartData])

  if (loading) return <p className="loading">Cargando…</p>
  if (error) return <Message type="error">{error}</Message>
  if (!sensor) return null

  return (
    <>
      <PageHeader
        title={`Gráficas: ${sensor.name}`}
        backTo={`/sensors/${sensorId}`}
        backLabel="Volver al sensor"
      />

      <Card title="Mediciones vs tiempo">
        <p className="text-muted small" style={{ marginBottom: '1rem' }}>
          Los datos se guardan al enviar mediciones (por ejemplo con <code>GET /api/data/ingest?key=...&variable=valor</code>).
          Aquí se muestran las últimas {CHART_LIMIT} mediciones por variable.
        </p>
        {variables.length === 0 ? (
          <p className="text-muted">
            Este sensor no tiene variables. <Link to={`/sensors/${sensorId}/variables`}>Definir variables</Link> y luego enviar datos para ver las gráficas.
          </p>
        ) : (
          <div className="charts-grid">
            {variables.map((v) => {
              const data = chartData[v.id] || []
              return (
                <Card key={v.id} title="">
                  <LineChart
                    data={data}
                    title={v.name}
                    unit={v.unit || undefined}
                    width={640}
                    height={240}
                  />
                </Card>
              )
            })}
          </div>
        )}
        <div style={{ marginTop: '1rem' }}>
          <button type="button" className="btn btn-secondary btn-sm" onClick={loadChartData}>
            Actualizar gráficas
          </button>
        </div>
      </Card>
    </>
  )
}
