import { Link } from 'react-router-dom'

/**
 * Encabezado de página con breadcrumb opcional.
 * items: [{ label, to? }, ...]
 */
export default function PageHeader({ title, breadcrumb, children }) {
  const items = Array.isArray(breadcrumb) ? breadcrumb : []
  return (
    <div className="page-header">
      {items.length > 0 && (
        <nav className="breadcrumb" aria-label="Navegación">
          {items.map((item, i) => (
            <span key={i}>
              {i > 0 && ' → '}
              {item.to ? <Link to={item.to}>{item.label}</Link> : <span>{item.label}</span>}
            </span>
          ))}
        </nav>
      )}
      {title && <h2>{title}</h2>}
      {children}
    </div>
  )
}
