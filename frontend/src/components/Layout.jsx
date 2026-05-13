import { Link } from 'react-router-dom'

/**
 * Layout principal: cabecera con navegación y contenido.
 */
export default function Layout({ children }) {
  return (
    <>
      <header className="app-header">
        <h1>CloudSensor</h1>
        <nav>
          <Link to="/">Dashboard</Link>
          <Link to="/sensors/new">Crear sensor</Link>
          <Link to="/alerts">Alertas</Link>
        </nav>
      </header>
      <main className="app-main">{children}</main>
    </>
  )
}
