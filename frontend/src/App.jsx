import { Routes, Route } from 'react-router-dom'
import Layout from './components/Layout'
import Dashboard from './pages/Dashboard'
import CreateSensor from './pages/CreateSensor'
import SensorDetail from './pages/SensorDetail'
import SensorVariables from './pages/SensorVariables'
import SensorCharts from './pages/SensorCharts'
import Formulas from './pages/Formulas'
import AlertRules from './pages/AlertRules'
import Alerts from './pages/Alerts'

function App() {
  return (
    <div className="app-layout">
      <Layout>
        <Routes>
          <Route path="/" element={<Dashboard />} />
          <Route path="/sensors/new" element={<CreateSensor />} />
          <Route path="/sensors/:sensorId" element={<SensorDetail />} />
          <Route path="/sensors/:sensorId/variables" element={<SensorVariables />} />
          <Route path="/sensors/:sensorId/charts" element={<SensorCharts />} />
          <Route path="/sensors/:sensorId/formulas" element={<Formulas />} />
          <Route path="/sensors/:sensorId/alert-rules" element={<AlertRules />} />
          <Route path="/alerts" element={<Alerts />} />
        </Routes>
      </Layout>
    </div>
  )
}

export default App
