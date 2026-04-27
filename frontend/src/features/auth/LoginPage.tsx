import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { Eye, EyeOff, Lock, User } from 'lucide-react'
import { useMutation } from '@tanstack/react-query'
import { login } from '@/api/auth'
import { useAuthStore } from '@/store/auth'
import { Button } from '@/components/ui/Button'
import axios from 'axios'

export function LoginPage() {
  const navigate = useNavigate()
  const setAuth = useAuthStore((s) => s.login)

  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [showPass, setShowPass] = useState(false)
  const [errorMsg, setErrorMsg] = useState('')

  const mutation = useMutation({
    mutationFn: () => login(username, password),
    onSuccess: (data) => {
      const payload = JSON.parse(atob(data.token.split('.')[1])) as {
        username?: string
        roles?: string[]
        sub?: string
      }
      const isAdmin = payload.roles?.includes('ROLE_ADMIN') ?? false
      const userId  = payload.sub ?? ''
      setAuth(data.token, isAdmin, payload.username ?? username, userId)
      navigate('/folders', { replace: true })
    },
    onError: (err) => {
      if (axios.isAxiosError(err) && err.response?.status === 401) {
        setErrorMsg('Identifiants invalides.')
      } else {
        setErrorMsg('Erreur de connexion. Réessayez.')
      }
    },
  })

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault()
    setErrorMsg('')
    mutation.mutate()
  }

  return (
    <div className="min-h-screen flex">
      {/* ── Panneau gauche — branding ──────────────────────────────────── */}
      <div className="hidden lg:flex lg:w-1/2 bg-[#141414] flex-col items-center justify-center p-12 relative overflow-hidden">
        {/* Pattern industriel en fond */}
        <div className="absolute inset-0 opacity-5"
          style={{
            backgroundImage: `repeating-linear-gradient(
              45deg,
              #F5A800 0px, #F5A800 1px,
              transparent 1px, transparent 20px
            )`,
          }}
        />
        <div className="relative z-10 text-center max-w-sm">
          <div className="flex items-center justify-center gap-5 mb-8">
            <img src="/logoEnof.jpeg" alt="ENOF" className="w-20 h-20 rounded-xl object-cover shadow-2xl" />
            <div className="w-px h-16 bg-white/20" />
            <img src="/logoSonarem.png" alt="Sonarem" className="w-20 h-20 rounded-full object-cover shadow-2xl" />
          </div>
          <h1 className="text-3xl font-black text-white tracking-tight mb-2">
             <span className="text-[#F5A800]">ENOF Spa</span>
          </h1>
          <p className="text-gray-400 text-sm leading-relaxed">
            Gestion Électronique de Documents<br />
            <span className="text-[#F5A800] font-semibold">Groupe Sonarem</span>
          </p>
          <div className="mt-10 grid grid-cols-3 gap-4 text-center">
            {[
              { label: 'Sécurisé', desc: 'Accès contrôlé' },
              { label: 'Traçable', desc: 'Audit complet' },
              { label: 'Efficace', desc: 'Zéro papier' },
            ].map((f) => (
              <div key={f.label} className="bg-white/5 rounded-lg p-3 border border-white/10">
                <p className="text-[#F5A800] font-bold text-sm">{f.label}</p>
                <p className="text-gray-500 text-xs mt-0.5">{f.desc}</p>
              </div>
            ))}
          </div>
        </div>
        {/* Bas de page */}
        <div className="absolute bottom-6 text-gray-700 text-xs tracking-wider">
          ENOF — Entreprise Nationale des Ossatures Ferrallées — {new Date().getFullYear()}
        </div>
      </div>

      {/* ── Panneau droit — formulaire ─────────────────────────────────── */}
      <div className="flex-1 flex flex-col items-center justify-center bg-[#F4F4F4] p-6">
        {/* Logo mobile */}
        <div className="lg:hidden flex flex-col items-center mb-8">
          <div className="flex items-center gap-3 mb-3">
            <img src="/logoEnof.jpeg" alt="ENOF" className="w-12 h-12 rounded-lg object-cover" />
            <img src="/logoSonarem.png" alt="Sonarem" className="w-12 h-12 rounded-full object-cover" />
          </div>
          <h1 className="text-xl font-black text-[#1A1A1A] tracking-tight">
            GED <span className="text-[#F5A800]">ENOF</span>
          </h1>
        </div>

        <div className="w-full max-w-sm">
          {/* Card */}
          <div className="bg-white rounded-lg shadow-lg border border-[#E8E8E8] overflow-hidden">
            {/* Barre jaune en haut */}
            <div className="h-1.5 w-full bg-[#F5A800]" />

            <div className="p-8">
              <h2 className="text-lg font-bold text-[#1A1A1A] mb-1 tracking-tight">
                Connexion
              </h2>
              <p className="text-sm text-[#888] mb-6">Accès réservé aux agents ENOF</p>

              <form onSubmit={handleSubmit} className="space-y-4">
                {/* Username */}
                <div>
                  <label className="block text-xs font-bold text-[#555] mb-1.5 uppercase tracking-wide">
                    Identifiant
                  </label>
                  <div className="relative">
                    <User size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-[#999]" />
                    <input
                      type="text"
                      required
                      autoFocus
                      value={username}
                      onChange={(e) => setUsername(e.target.value)}
                      className="w-full pl-9 pr-3.5 py-2.5 rounded-md border border-[#D0D0D0] text-sm
                                 text-[#1A1A1A] bg-[#FAFAFA]
                                 focus:outline-none focus:ring-2 focus:ring-[#F5A800] focus:border-[#F5A800]
                                 transition-all placeholder-[#BBBBBB]"
                      placeholder="nom.prenom"
                    />
                  </div>
                </div>

                {/* Password */}
                <div>
                  <label className="block text-xs font-bold text-[#555] mb-1.5 uppercase tracking-wide">
                    Mot de passe
                  </label>
                  <div className="relative">
                    <Lock size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-[#999]" />
                    <input
                      type={showPass ? 'text' : 'password'}
                      required
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      className="w-full pl-9 pr-10 py-2.5 rounded-md border border-[#D0D0D0] text-sm
                                 text-[#1A1A1A] bg-[#FAFAFA]
                                 focus:outline-none focus:ring-2 focus:ring-[#F5A800] focus:border-[#F5A800]
                                 transition-all placeholder-[#BBBBBB]"
                      placeholder="••••••••"
                    />
                    <button
                      type="button"
                      onClick={() => setShowPass(!showPass)}
                      className="absolute right-3 top-1/2 -translate-y-1/2 text-[#AAA] hover:text-[#555] transition-colors"
                    >
                      {showPass ? <EyeOff size={15} /> : <Eye size={15} />}
                    </button>
                  </div>
                </div>

                {errorMsg && (
                  <div className="flex items-center gap-2 text-sm text-red-700 bg-red-50 border border-red-100 px-3 py-2.5 rounded-md">
                    <div className="w-1.5 h-1.5 bg-red-500 rounded-full shrink-0" />
                    {errorMsg}
                  </div>
                )}

                <Button
                  type="submit"
                  className="w-full mt-2"
                  size="lg"
                  loading={mutation.isPending}
                >
                  Se connecter
                </Button>
              </form>
            </div>
          </div>

          <p className="text-center text-xs text-[#BBBBBB] mt-6">
            © {new Date().getFullYear()} ENOF — Groupe Sonarem. Tous droits réservés.
          </p>
        </div>
      </div>
    </div>
  )
}
