/**
 * Mensaje de estado: éxito o error.
 * Uso: <Message type="success">Guardado correctamente</Message>
 */
export default function Message({ type = 'error', children, onDismiss }) {
  const className = `message ${type}`
  return (
    <div className={className} role="alert">
      {children}
      {onDismiss && (
        <button
          type="button"
          onClick={onDismiss}
          className="message-dismiss"
          aria-label="Cerrar"
        >
          ×
        </button>
      )}
    </div>
  )
}
