import { useState, type FormEvent } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import axios from 'axios'
import { createUser } from '@/api/adminUsers'
import { Button } from '@/components/ui/Button'
import { Modal } from '@/components/ui/Modal'

interface Props {
  open: boolean
  onClose: () => void
}

export function CreateUserModal({ open, onClose }: Props) {
  const qc = useQueryClient()
  const [username, setUsername] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [isAdmin, setIsAdmin] = useState(false)
  const [errorMsg, setErrorMsg] = useState('')

  const reset = () => {
    setUsername('')
    setEmail('')
    setPassword('')
    setIsAdmin(false)
    setErrorMsg('')
  }

  const mutation = useMutation({
    mutationFn: () => createUser({ username, email, password, isAdmin }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin', 'users'] })
      toast.success('Compte créé avec succès.')
      reset()
      onClose()
    },
    onError: (error: unknown) => {
      if (axios.isAxiosError(error) && typeof error.response?.data?.error === 'string') {
        setErrorMsg(error.response.data.error)
        return
      }

      setErrorMsg('Impossible de créer le compte.')
    },
  })

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault()
    setErrorMsg('')
    mutation.mutate()
  }

  const handleClose = () => {
    if (mutation.isPending) return
    reset()
    onClose()
  }

  return (
    <Modal open={open} onClose={handleClose} title="Créer un compte">
      <form onSubmit={handleSubmit} className="space-y-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1.5">Nom d'utilisateur</label>
          <input
            autoFocus
            type="text"
            value={username}
            onChange={(e) => setUsername(e.target.value)}
            className="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            placeholder="ex: sara"
            required
            minLength={3}
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
          <input
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            className="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            placeholder="ex: sara@example.com"
            required
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1.5">Mot de passe</label>
          <input
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            className="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            placeholder="8 caractères minimum"
            required
            minLength={8}
          />
        </div>

        <label className="flex items-center gap-3 rounded-lg border border-gray-200 px-3.5 py-3 cursor-pointer">
          <input
            type="checkbox"
            checked={isAdmin}
            onChange={(e) => setIsAdmin(e.target.checked)}
            className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
          />
          <div>
            <p className="text-sm font-medium text-gray-800">Compte administrateur</p>
            <p className="text-xs text-gray-500">Donne accès aux actions d'administration.</p>
          </div>
        </label>

        {errorMsg && (
          <p className="text-sm text-red-600 bg-red-50 px-3 py-2 rounded-lg">{errorMsg}</p>
        )}

        <div className="flex justify-end gap-2 pt-2">
          <Button variant="secondary" type="button" onClick={handleClose}>
            Annuler
          </Button>
          <Button type="submit" loading={mutation.isPending}>
            Créer le compte
          </Button>
        </div>
      </form>
    </Modal>
  )
}
