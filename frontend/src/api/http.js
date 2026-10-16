import axios from 'axios'

export const TOKEN_KEY = 'disarlsoft.token'
export const TOKEN_EMITIDO_KEY = 'disarlsoft.token_emitido'

const http = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  headers: { Accept: 'application/json' },
  timeout: 20000,
})

// El token se lee en cada petición: si otra pestaña lo renueva, esta ya usa el nuevo.
http.interceptors.request.use((config) => {
  const token = localStorage.getItem(TOKEN_KEY)
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

// El store de sesión registra aquí qué hacer cuando el servidor responde 401.
// Se hace con un callback para no crear un import circular entre http y el store.
let alExpirarSesion = () => {}
export function registrarSesionExpirada(fn) {
  alExpirarSesion = fn
}

http.interceptors.response.use(
  (respuesta) => respuesta,
  (error) => {
    const sinLogin = !(error.config?.url ?? '').includes('auth/login')
    if (error.response?.status === 401 && sinLogin) alExpirarSesion()
    return Promise.reject(error)
  },
)

// Convierte un error de axios en un mensaje que el usuario pueda entender.
export function mensajeError(error, porDefecto = 'No se pudo completar la operación. Intenta de nuevo.') {
  if (!error.response) {
    return 'No hay conexión con el servidor. Verifica que el sistema esté encendido.'
  }

  const { status, data } = error.response

  if (status === 401) return 'Usuario o contraseña incorrectos. Revisa los datos e intenta de nuevo.'
  if (status === 422 && data?.errors) return Object.values(data.errors).flat()[0]

  return data?.message ?? porDefecto
}

export default http