import { useEffect, useState } from 'react'
import './App.css'

function App() {
  const [apiStatus, setApiStatus] = useState<'checking' | 'ok' | 'error'>('checking')

  useEffect(() => {
    fetch('/api/test')
      .then((res) => setApiStatus(res.ok ? 'ok' : 'error'))
      .catch(() => setApiStatus('error'))
  }, [])

  return (
    <>
      <h1>Pouch</h1>
      <p className="read-the-docs">
        Backend status: <strong>{apiStatus}</strong>
      </p>
    </>
  )
}

export default App
