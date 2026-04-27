import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { X, Lock, LockOpen, UserPlus, Trash2, Shield, Eye, Pencil } from 'lucide-react'
import { toast } from 'sonner'
import {
  getFolderPermissions,
  setFolderPermission,
  removeFolderPermission,
  setFolderRestricted,
} from '@/api/folderPermissions'
import { listUsers } from '@/api/adminUsers'
import type { PermissionLevel } from '@/types'

interface Props {
  folderId: string
  folderName: string
  onClose: () => void
}

export function PermissionsModal({ folderId, folderName, onClose }: Props) {
  const qc = useQueryClient()
  const [selectedUserId, setSelectedUserId] = useState('')
  const [selectedLevel, setSelectedLevel] = useState<PermissionLevel>('read')

  const { data: perms, isLoading } = useQuery({
    queryKey: ['folder-permissions', folderId],
    queryFn: () => getFolderPermissions(folderId),
  })

  const { data: allUsers = [] } = useQuery({
    queryKey: ['admin-users'],
    queryFn: listUsers,
  })

  const invalidate = () => {
    qc.invalidateQueries({ queryKey: ['folder-permissions', folderId] })
    qc.invalidateQueries({ queryKey: ['folder', folderId] })
    qc.invalidateQueries({ queryKey: ['folder', 'root'] })
  }

  const restrictMut = useMutation({
    mutationFn: (restricted: boolean) => setFolderRestricted(folderId, restricted),
    onSuccess: (_, restricted) => {
      toast.success(restricted ? 'Dossier restreint.' : 'Dossier ouvert à tous.')
      invalidate()
    },
    onError: () => toast.error('Erreur lors de la modification.'),
  })

  const grantMut = useMutation({
    mutationFn: () => setFolderPermission(folderId, selectedUserId, selectedLevel),
    onSuccess: () => {
      toast.success('Permission accordée.')
      setSelectedUserId('')
      invalidate()
    },
    onError: () => toast.error('Erreur lors de l\'ajout.'),
  })

  const revokeMut = useMutation({
    mutationFn: (userId: string) => removeFolderPermission(folderId, userId),
    onSuccess: () => {
      toast.success('Permission supprimée.')
      invalidate()
    },
    onError: () => toast.error('Erreur lors de la suppression.'),
  })

  // Utilisateurs sans permission existante
  const permittedIds = new Set((perms?.permissions ?? []).map((p) => p.userId))
  const availableUsers = allUsers.filter((u) => !permittedIds.has(u.id))

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-lg flex flex-col max-h-[90vh]">

        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
          <div className="flex items-center gap-3">
            <div className="w-9 h-9 rounded-xl bg-indigo-100 flex items-center justify-center">
              <Shield size={18} className="text-indigo-600" />
            </div>
            <div>
              <h2 className="text-base font-bold text-gray-900">Permissions</h2>
              <p className="text-xs text-gray-500 truncate max-w-[240px]">{folderName}</p>
            </div>
          </div>
          <button onClick={onClose} className="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition-colors">
            <X size={18} />
          </button>
        </div>

        {isLoading ? (
          <div className="flex-1 flex items-center justify-center py-12 text-gray-400 text-sm">Chargement…</div>
        ) : (
          <div className="flex-1 overflow-y-auto px-6 py-4 space-y-6">

            {/* Toggle accès restreint */}
            <div className="flex items-center justify-between p-4 rounded-xl border border-gray-200 bg-gray-50">
              <div className="flex items-center gap-3">
                {perms?.restricted
                  ? <Lock size={18} className="text-red-500" />
                  : <LockOpen size={18} className="text-green-500" />
                }
                <div>
                  <p className="text-sm font-semibold text-gray-800">
                    {perms?.restricted ? 'Accès restreint' : 'Accès ouvert'}
                  </p>
                  <p className="text-xs text-gray-500">
                    {perms?.restricted
                      ? 'Seuls les utilisateurs listés ci-dessous peuvent accéder.'
                      : 'Tous les membres de la GED peuvent voir ce dossier.'}
                  </p>
                </div>
              </div>
              <button
                onClick={() => restrictMut.mutate(!perms?.restricted)}
                disabled={restrictMut.isPending}
                className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none ${
                  perms?.restricted ? 'bg-red-500' : 'bg-green-400'
                }`}
              >
                <span className={`inline-block h-4 w-4 rounded-full bg-white shadow transition-transform ${
                  perms?.restricted ? 'translate-x-6' : 'translate-x-1'
                }`} />
              </button>
            </div>

            {/* Liste des permissions */}
            {perms?.restricted && (
              <div className="space-y-3">
                <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider">Accès explicites</p>

                {(perms?.permissions ?? []).length === 0 ? (
                  <div className="text-center py-6 text-sm text-gray-400">
                    Aucun utilisateur autorisé — personne ne peut accéder à ce dossier (sauf admins).
                  </div>
                ) : (
                  <div className="space-y-2">
                    {(perms?.permissions ?? []).map((p) => (
                      <div key={p.userId} className="flex items-center justify-between px-4 py-2.5 rounded-xl bg-gray-50 border border-gray-200">
                        <div className="flex items-center gap-2.5">
                          <div className="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold text-sm">
                            {p.username[0]?.toUpperCase()}
                          </div>
                          <span className="text-sm font-medium text-gray-800">{p.username}</span>
                        </div>
                        <div className="flex items-center gap-2">
                          <span className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold ${
                            p.level === 'write'
                              ? 'bg-indigo-100 text-indigo-700'
                              : 'bg-amber-100 text-amber-700'
                          }`}>
                            {p.level === 'write'
                              ? <><Pencil size={10} /> Écriture</>
                              : <><Eye size={10} /> Lecture</>
                            }
                          </span>
                          {/* Changer le niveau */}
                          <select
                            value={p.level}
                            onChange={(e) => {
                              setFolderPermission(folderId, p.userId, e.target.value as PermissionLevel)
                                .then(() => { toast.success('Niveau mis à jour.'); invalidate() })
                                .catch(() => toast.error('Erreur'))
                            }}
                            className="text-xs border border-gray-200 rounded-lg px-2 py-1 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-300"
                          >
                            <option value="read">Lecture</option>
                            <option value="write">Écriture</option>
                          </select>
                          <button
                            onClick={() => revokeMut.mutate(p.userId)}
                            disabled={revokeMut.isPending}
                            className="p-1.5 rounded-lg text-red-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                            title="Révoquer l'accès"
                          >
                            <Trash2 size={14} />
                          </button>
                        </div>
                      </div>
                    ))}
                  </div>
                )}

                {/* Ajouter un utilisateur */}
                <div className="pt-2 border-t border-gray-100">
                  <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Ajouter un accès</p>
                  <div className="flex gap-2">
                    <select
                      value={selectedUserId}
                      onChange={(e) => setSelectedUserId(e.target.value)}
                      className="flex-1 text-sm border border-gray-200 rounded-xl px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-300"
                    >
                      <option value="">Sélectionner un utilisateur…</option>
                      {availableUsers.map((u) => (
                        <option key={u.id} value={u.id}>{u.username}</option>
                      ))}
                    </select>
                    <select
                      value={selectedLevel}
                      onChange={(e) => setSelectedLevel(e.target.value as PermissionLevel)}
                      className="text-sm border border-gray-200 rounded-xl px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-300"
                    >
                      <option value="read">Lecture</option>
                      <option value="write">Écriture</option>
                    </select>
                    <button
                      onClick={() => grantMut.mutate()}
                      disabled={!selectedUserId || grantMut.isPending}
                      className="flex items-center gap-1.5 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                      <UserPlus size={14} /> Ajouter
                    </button>
                  </div>
                </div>
              </div>
            )}
          </div>
        )}

        {/* Footer */}
        <div className="px-6 py-3 border-t border-gray-100 flex justify-end">
          <button
            onClick={onClose}
            className="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-xl transition-colors"
          >
            Fermer
          </button>
        </div>
      </div>
    </div>
  )
}
