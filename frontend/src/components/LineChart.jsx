/**
 * Gráfico de líneas: valor vs tiempo (mediciones).
 * data = [{ measured_at: string, value: number }, ...] ordenado por tiempo ASC.
 */
export default function LineChart({ data = [], title, unit = '', width = 600, height = 220 }) {
  if (!Array.isArray(data) || data.length === 0) {
    return (
      <div className="line-chart-wrap">
        {title && <h4 className="line-chart-title">{title}{unit ? ` (${unit})` : ''}</h4>}
        <p className="text-muted small">Sin datos para graficar</p>
      </div>
    )
  }

  const padding = { top: 16, right: 16, bottom: 28, left: 44 }
  const innerWidth = width - padding.left - padding.right
  const innerHeight = height - padding.top - padding.bottom

  const values = data.map((d) => Number(d.value))
  const minV = Math.min(...values)
  const maxV = Math.max(...values)
  const range = maxV - minV || 1
  const scaleY = (v) => padding.top + innerHeight - ((v - minV) / range) * innerHeight

  const times = data.map((d) => new Date(d.measured_at).getTime())
  const minT = Math.min(...times)
  const maxT = Math.max(...times)
  const rangeT = maxT - minT || 1
  const scaleX = (t) => padding.left + ((t - minT) / rangeT) * innerWidth

  const points = data.map((d) => `${scaleX(new Date(d.measured_at).getTime())},${scaleY(Number(d.value))}`).join(' ')
  const pathD = points ? `M ${points.replace(/\s/g, ' L ')}` : ''

  const formatTime = (ms) => {
    const d = new Date(ms)
    return d.toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' })
  }
  const formatValue = (v) => (Number.isInteger(v) ? v : v.toFixed(2))

  return (
    <div className="line-chart-wrap">
      {title && <h4 className="line-chart-title">{title}{unit ? ` (${unit})` : ''}</h4>}
      <svg width={width} height={height} className="line-chart-svg" viewBox={`0 0 ${width} ${height}`}>
        <defs>
          <linearGradient id="lineGrad" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor="var(--chart-line, #2563eb)" stopOpacity="0.3" />
            <stop offset="100%" stopColor="var(--chart-line, #2563eb)" stopOpacity="0" />
          </linearGradient>
        </defs>
        {/* Eje Y: valores */}
        <line x1={padding.left} y1={padding.top} x2={padding.left} y2={height - padding.bottom} stroke="#e5e7eb" strokeWidth="1" />
        <text x={padding.left - 6} y={padding.top} textAnchor="end" className="line-chart-axis" fontSize="10">{formatValue(maxV)}</text>
        <text x={padding.left - 6} y={height - padding.bottom} textAnchor="end" className="line-chart-axis" fontSize="10">{formatValue(minV)}</text>
        {/* Eje X: tiempo */}
        <line x1={padding.left} y1={height - padding.bottom} x2={width - padding.right} y2={height - padding.bottom} stroke="#e5e7eb" strokeWidth="1" />
        <text x={padding.left} y={height - 6} textAnchor="middle" className="line-chart-axis" fontSize="10">{formatTime(minT)}</text>
        <text x={width - padding.right} y={height - 6} textAnchor="middle" className="line-chart-axis" fontSize="10">{formatTime(maxT)}</text>
        {/* Área bajo la línea */}
        {pathD && (
          <path
            d={`${pathD} L ${scaleX(maxT)} ${height - padding.bottom} L ${padding.left} ${height - padding.bottom} Z`}
            fill="url(#lineGrad)"
          />
        )}
        {/* Línea */}
        {pathD && (
          <path d={pathD} fill="none" stroke="var(--chart-line, #2563eb)" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
        )}
      </svg>
    </div>
  )
}
