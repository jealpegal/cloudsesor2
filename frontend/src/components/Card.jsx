/**
 * Tarjeta contenedora con título opcional.
 */
export default function Card({ title, children, className = '' }) {
  return (
    <div className={`card ${className}`.trim()}>
      {title && <h2>{title}</h2>}
      {children}
    </div>
  )
}
