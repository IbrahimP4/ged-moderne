import { useState, type FormEvent } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { createFolder } from '@/api/folders'
import { Modal } from '@/components/ui/Modal'
import { Button } from '@/components/ui/Button'

interface Props {
  open: boolean
  onClose: () => void
  parentId?: string
}

export function CreateFolderModal({ open, onClose, parentId }: Props) {
  const [name, setName] = useState('')
  const qc = useQueryClient()

  const mutation = useMutation({
    mutationFn: () => createFolder(name, parentId),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['folder'] })
      toast.success(`Dossier "${name}" créé.`)
      setName('')
      onClose()
    },
    onError: () => toast.error('Impossible de créer le dossier.'),
  })

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault()
    if (name.trim()) mutation.mutate()
  }

  return (
    <Modal open={open} onClose={onClose} title="Nouveau dossier">
      <form onSubmit={handleSubmit} className="space-y-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1.5">Nom du dossier</label>
          <input
            autoFocus
            type="text"
            value={name}
            onChange={(e) => setName(e.target.value)}
            className="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            placeholder="Ex: Contrats 2026"
          />
        </div>
        <div className="flex justify-end gap-2 pt-2">
          <Button variant="secondary" type="button" onClick={onClose}>Annuler</Button>
          <Button type="submit" loading={mutation.isPending} disabled={!name.trim()}>
            Créer
          </Button>
        </div>
      </form>
    </Modal>
  )
}
