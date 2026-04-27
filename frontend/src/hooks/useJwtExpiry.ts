import { useEffect } from 'react'
import { toast } from 'sonner'
import { useAuthStore } from '@/store/auth'

export function useJwtExpiry() {
  const { token, logout } = useAuthStore()

  useEffect(() => {
    if (!token) return

    try {
      const payload = JSON.parse(atob(token.split('.')[1]))
      const expiresAt = payload.exp * 1000
      const now = Date.now()
      const msUntilExpiry = expiresAt - now

      if (msUntilExpiry <= 0) { logout(); return }

      // Avertissement 5 minutes avant expiration
      const warningMs = msUntilExpiry - 5 * 60 * 1000
      const warningTimer = warningMs > 0
        ? setTimeout(() => {
            toast.warning('Votre session expire dans 5 minutes. Reconnectez-vous pour continuer.', {
              duration: 10000,
            })
          }, warningMs)
        : null

      // Déconnexion automatique à expiration
      const logoutTimer = setTimeout(() => {
        toast.error('Votre session a expiré. Veuillez vous reconnecter.')
        logout()
      }, msUntilExpiry)

      return () => {
        if (warningTimer) clearTimeout(warningTimer)
        clearTimeout(logoutTimer)
      }
    } catch {
      // Token malformé
    }
  }, [token, logout])
}
