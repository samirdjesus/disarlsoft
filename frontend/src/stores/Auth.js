import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import http, { TOKEN_EMITIDO_KEY, TOKEN_KEY, registrarSesionExpirada } from '@/api/http'

const USUARIO_KEY = 'disarlsoft.usuario'
const ALMACEN_KEY = 'disarlsoft.almacen'

function leerJSON(clave) {
  try {
    return JSON.parse(localStorage.getItem(clave))
  } catch {
    return null
  }
}

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem(TOKEN_KEY))
  const usuario = ref(leerJSON(USUARIO_KEY))
  const almacenActivoId = ref(Number(localStorage.getItem(ALMACEN_KEY)) || null)
  const sesionVerificada = ref(false) // true cuando /auth/me confirmó el token en esta carga de la página
  const motivoSalida = ref(null) // 'manual' | 'expirada' | null

  const autenticado = computed(() => !!token.value)
  const esAdmin = computed(() => !!usuario.value?.es_admin)
  const almacenes = computed(() => usuario.value?.almacenes ?? [])
  const almacenActivo = computed(
    () => almacenes.value.find((a) => a.id_almacen === almacenActivoId.value) ?? almacenes.value[0] ?? null,
  )

  // ¿Puede el usuario hacer {accion} en el módulo {modulo}? Los permisos llegan del backend.
  function can(modulo, accion = 'ver') {
    if (!usuario.value) return false
    return esAdmin.value || !!usuario.value.permisos?.[modulo]?.[accion]
  }

  function guardarToken(nuevo) {
    token.value = nuevo
    localStorage.setItem(TOKEN_KEY, nuevo)
    localStorage.setItem(TOKEN_EMITIDO_KEY, String(Date.now()))
  }

  function guardarUsuario(nuevo) {
    usuario.value = nuevo
    localStorage.setItem(USUARIO_KEY, JSON.stringify(nuevo))

    // Si el almacén elegido ya no está entre los del usuario, vuelve al principal
    const sigueVigente = nuevo.almacenes?.some((a) => a.id_almacen === almacenActivoId.value)
    if (!sigueVigente) seleccionarAlmacen(nuevo.almacen_principal ?? nuevo.almacenes?.[0]?.id_almacen ?? null)
  }

  function seleccionarAlmacen(id) {
    almacenActivoId.value = id
    if (id) localStorage.setItem(ALMACEN_KEY, String(id))
    else localStorage.removeItem(ALMACEN_KEY)
  }

  async function login(nomUsuario, contrasena) {
    const { data } = await http.post('auth/login', { nom_usuario: nomUsuario, contrasena })
    guardarToken(data.token)
    guardarUsuario(data.usuario)
    sesionVerificada.value = true
    motivoSalida.value = null
  }

  // Revalida el token y trae permisos al día (por si el administrador los cambió)
  async function cargarSesion() {
    const { data } = await http.get('auth/me')
    guardarUsuario(data)
    sesionVerificada.value = true
  }

  async function refrescarToken() {
    const { data } = await http.post('auth/refresh')
    guardarToken(data.token)
  }

  function limpiar() {
    token.value = null
    usuario.value = null
    almacenActivoId.value = null
    sesionVerificada.value = false
    for (const clave of [TOKEN_KEY, TOKEN_EMITIDO_KEY, USUARIO_KEY, ALMACEN_KEY]) {
      localStorage.removeItem(clave)
    }
  }

  function cerrarSesion(motivo = 'manual') {
    if (!token.value) return // ya estaba cerrada: evita repetir el cierre desde varios lados

    const tokenAnterior = token.value
    motivoSalida.value = motivo
    limpiar()

    // Solo en el cierre manual se invalida el token en el servidor (el expirado ya no sirve)
    if (motivo === 'manual') {
      http.post('auth/logout', null, { headers: { Authorization: `Bearer ${tokenAnterior}` } }).catch(() => {})
    }
  }

  registrarSesionExpirada(() => cerrarSesion('expirada'))

  // Sincroniza varias pestañas: si una cierra sesión o cambia de almacén, las demás se enteran
  window.addEventListener('storage', (e) => {
    if (e.key === TOKEN_KEY) {
      token.value = e.newValue
      if (!e.newValue) {
        usuario.value = null
        sesionVerificada.value = false
      }
    } else if (e.key === USUARIO_KEY) {
      usuario.value = leerJSON(USUARIO_KEY)
    } else if (e.key === ALMACEN_KEY) {
      almacenActivoId.value = Number(e.newValue) || null
    }
  })

  return {
    token, usuario, almacenActivoId, sesionVerificada, motivoSalida,
    autenticado, esAdmin, almacenes, almacenActivo,
    can, login, cargarSesion, refrescarToken, cerrarSesion, seleccionarAlmacen,
  }
})